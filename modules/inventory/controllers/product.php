<?php
/**
 * @filesource modules/inventory/controllers/product.php
 *
 * api/inventory/product/get|save|delete — ฟอร์มทะเบียนพัสดุ
 *
 * ⚠️ สัญญากับหน้าเว็บ (MODULE_DEVELOPMENT_GUIDE §4)
 *   get()  ต้องคืน successResponse(['data' => $record, 'options' => [...]])
 *          ค่าของฟอร์มอ่านจาก data.data และตัวเลือกอ่านจาก data.options[<ชื่อ field>]
 *          **ห้ามทำให้แบน** ฟอร์มจะโหลดขึ้นมาว่างเปล่าโดยไม่มีข้อความเตือน
 *   save() ผิดพลาดใช้ formErrorResponse($errors, 400) โดยคีย์ของ errors ต้องตรง
 *          กับ name ของช่อง สำเร็จใช้ redirectResponse()
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Product;

use Gcms\Api as ApiController;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API ทะเบียนพัสดุ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/inventory/product/get
     *
     * @param Request $request
     *
     * @return Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_inventory')) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->get('id')->toInt();
            $product = $id > 0 ? Model::get($id) : self::blank();
            if ($product === null) {
                return $this->errorResponse('No data available', 404);
            }

            $categories = \Inventory\Category\Controller::init();

            foreach (['category_id', 'model_id', 'type_id'] as $_key) {
                $product[$_key] = [
                    'value' => (string) $product[$_key],
                    'text' => $categories->get($_key, $product[$_key], '')
                ];
            }

            // รูปของพัสดุ เก็บที่ datas/inventory/{id}.{ext} เหมือนระบบเดิม
            if ($id > 0) {
                $image = Model::imageUrl($id);
                if ($image !== null) {
                    $product['image'] = [
                        'url' => $image,
                        'name' => $id.self::$cfg->stored_img_type
                    ];
                }
            }

            return $this->successResponse([
                'data' => $product,
                'options' => [
                    'category_id' => $categories->toOptions('category_id'),
                    'model_id' => $categories->toOptions('model_id'),
                    'type_id' => $categories->toOptions('type_id'),
                    'unit' => array_values($categories->toOptions('unit', false))
                ]
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST api/inventory/product/save
     *
     * @param Request $request
     *
     * @return Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_inventory')) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id')->toInt();

            $data = [];
            foreach ([
                'topic' => 'topic',
                'description' => 'textarea',
                'category_id' => 'topic',
                'model_id' => 'topic',
                'type_id' => 'topic',
                'unit' => 'topic',
                'is_active' => 'toBoolean'
            ] as $_field => $_filter) {
                $data[$_field] = $request->post($_field)->{$_filter}();
            }
            if (!isset($data['topic'])) {
                $data['topic'] = '';
            }

            // เลขครุภัณฑ์รหัสแรกกรอกในฟอร์มเดียวกันตอนสร้างรายการใหม่ แบบต้นฉบับ
            // (ของเดิมบังคับกรอกทั้งรหัส หน่วยนับ และจำนวน)
            $productNo = $request->post('product_no')->topic();
            $stock = $request->post('stock')->toDouble();
            $unit = isset($data['unit']) ? $data['unit'] : '';

            $errors = [];
            if ($data['topic'] === '') {
                $errors['topic'] = 'Please fill in';
            }
            if ($id === 0 && $productNo === '') {
                $errors['product_no'] = 'Please fill in';
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            // หมวดหมู่ · ยี่ห้อ · ประเภท เป็นช่อง autocomplete ที่ส่งค่ามาในชื่อ
            // เดียวกันทั้งสองแบบ — id ของรายการที่เลือกจากรายการ หรือ "ชื่อ" ที่
            // ผู้ใช้พิมพ์เองเมื่อไม่ได้เลือก (Now.js ใส่ข้อความที่พิมพ์ลงในค่าที่ส่ง)
            //
            // ⚠️ ชื่อที่ยังไม่มีต้องสร้างหมวดหมู่ให้ก่อนเสมอ ถ้าเขียนลงตารางตรง ๆ
            // ข้อความจะไปอยู่ในคอลัมน์ varchar(10) ที่เก็บ id (ชื่อยาวกว่านั้นถูก
            // ตัดทิ้ง) แล้วหน้าฟอร์มจะแสดงช่องว่างเพราะหา id นั้นไม่เจอ
            // ทางเดียวกับการนำเข้าไฟล์ Inventory\Import\Model::importProduct()
            //
            // อ่านสดไม่เอาแคช — แคชคิวรีของแกนอายุสั้นแต่ก็ยังคลาดกันได้
            // ถ้าอ่านรายการเก่า id ที่เพิ่งถูกสร้างจะถูกมองว่า "ยังไม่มี"
            $categories = \Inventory\Category\Controller::init(true, true, false);
            foreach (['category_id', 'model_id', 'type_id'] as $_type) {
                if ($data[$_type] !== '' && !$categories->exists($_type, $data[$_type])) {
                    $data[$_type] = (string) \Inventory\Category\Controller::save($_type, $data[$_type]);
                }
            }

            // หน่วยนับต่างจากสามช่องบน — เก็บเป็น "ชื่อ" ลงคอลัมน์ unit ตรง ๆ
            // (toOptions('unit', false) ใช้ชื่อเป็นทั้ง value และ text) ชื่อใหม่จึง
            // บันทึกได้อยู่แล้วไม่พัง แต่ถ้าไม่จดไว้ รายการแนะนำจะไม่โตตามของจริง
            // ทิ้งค่าที่คืนมาเพราะคอลัมน์เก็บชื่อไม่ใช่ id และ save() คืน id เดิม
            // เมื่อชื่อซ้ำ จึงเรียกได้ทุกครั้งโดยไม่เกิดหมวดหมู่ซ้ำ
            if ($data['unit'] !== '') {
                \Inventory\Category\Controller::save('unit', $data['unit']);
            }

            if ($id > 0) {
                Model::updateProduct($id, $data);
            } else {
                $id = Model::createProduct($data);
                try {
                    Model::saveItem($id, [
                        'product_no' => $productNo,
                        'unit' => $unit,
                        'stock' => $stock
                    ]);
                } catch (\Exception $_e) {
                    // รหัสซ้ำ — พัสดุถูกสร้างไปแล้ว ต้องเก็บกวาดก่อนแจ้งกลับ
                    // ไม่งั้นจะเหลือรายการที่ไม่มีเลขครุภัณฑ์ค้างอยู่ในทะเบียน
                    Model::remove($id);

                    return $this->formErrorResponse(['product_no' => $_e->getMessage()], 400);
                }
            }

            // Upload file
            foreach ($request->getUploadedFiles() as $_name => $_file) {
                if ($_name !== 'image') {
                    continue;
                }
                if ($_file->hasUploadFile()) {
                    $_dir = Model::imageDir();
                    if (!File::makeDirectory($_dir)) {
                        return $this->formErrorResponse([
                            'image' => Language::replace(
                                'Directory %s cannot be created or is read-only.',
                                DATA_FOLDER.'inventory/'
                            )
                        ], 400);
                    }
                    try {
                        $_file->resizeImage(
                            self::$cfg->img_typies,
                            $_dir,
                            Model::imageName($id),
                            self::$cfg->inventory_w
                        );
                    } catch (\Exception $_exc) {
                        return $this->formErrorResponse(['image' => $_exc->getMessage()], 400);
                    }
                } elseif ($_file->hasError()) {
                    return $this->formErrorResponse(['image' => $_file->getErrorMessage()], 400);
                }
            }

            \Index\Log\Model::add($id, 'inventory', 'Save',
                '{LNG_Equipment} ID : '.$id, $login->id);

            return $this->redirectResponse('/inventory-setup', 'Saved successfully', 200, 1000);
        } catch (\Exception $e) {
            // ข้อผิดพลาดที่โมเดลโยนมาเป็นเรื่องของช่องใดช่องหนึ่งเสมอ (รหัสซ้ำ ชื่อว่าง)
            return $this->formErrorResponse(['topic' => $e->getMessage()], 400);
        }
    }

    /**
     * POST api/inventory/product/remove-image — ลบรูปพัสดุ
     *
     * ช่องอัปโหลดของแกนยิงมาที่ data-action-url เมื่อผู้ใช้กดลบไฟล์เดิม
     *
     * @param Request $request
     *
     * @return Response
     */
    public function removeImage(Request $request)
    {
        try {
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::canModify($login, 'can_manage_inventory')) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->request('id')->toInt();
            if ($id <= 0 || Model::get($id) === null) {
                return $this->errorResponse('No data available', 404);
            }

            Model::removeImage($id);
            \Index\Log\Model::add($id, 'inventory', 'Delete',
                '{LNG_Image} ID : '.$id, $login->id);

            return $this->successResponse([], 'Deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * ค่าเริ่มต้นของพัสดุใหม่
     *
     * @return array
     */
    protected static function blank()
    {
        return [
            'id' => 0,
            'topic' => '',
            'description' => '',
            'category_id' => '',
            'model_id' => '',
            'type_id' => '',
            'unit' => '',
            'product_no' => '',
            'stock' => 0,
            'is_active' => 1,
            'balance' => 0,
            'items' => []
        ];
    }
}
