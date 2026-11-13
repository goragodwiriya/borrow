<?php
/**
 * @filesource modules/inventory/controllers/items.php
 *
 * api/inventory/items/get|save — เลขครุภัณฑ์/บาร์โค้ดของพัสดุหนึ่งรายการ
 * ระบบเดิมคือ module=inventory-write&tab=items
 *
 * ใช้แพทเทิร์นตารางแก้ไขทั้งตารางแล้วกดบันทึกครั้งเดียว (เหมือน categories)
 * ผู้ใช้จึงเพิ่ม/แก้/ลบหลายรหัสพร้อมกันได้แบบระบบเดิม
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Items;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;

/**
 * API เลขครุภัณฑ์/บาร์โค้ด
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/inventory/items/get?id=N
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
            $product = \Inventory\Product\Model::get($id);
            if ($product === null || $id <= 0) {
                return $this->errorResponse('No data available', 404);
            }

            return $this->successResponse([
                'data' => [
                    'id' => (int) $product['id'],
                    'topic' => $product['topic'],
                    'options' => [
                        'data' => Model::toDataTable($product)
                    ]
                ]
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), 400);
        }
    }

    /**
     * POST api/inventory/items/save
     *
     * ตารางแก้ไขส่งค่ามาเป็นอาเรย์รายคอลัมน์ (product_no[], topic[], ...)
     * แบบเดียวกับหน้าหมวดหมู่ของแกน ไม่ใช่ items[0][field]
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
            if (!ApiController::canModify($login, 'can_manage_inventory')) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id')->toInt();
            $product = \Inventory\Product\Model::get($id);
            if ($product === null || $id <= 0) {
                return $this->errorResponse('No data available', 404);
            }

            $productNos = $request->post('product_no', [])->topic();
            $topics = $request->post('topic', [])->topic();
            $stocks = $request->post('stock', [])->toFloat();
            $units = $request->post('unit', [])->topic();
            $tables = $request->post('_editable_tables', [])->topic();
            $table = $tables[0] ?? '';

            $rows = [];
            foreach ($productNos as $index => $productNo) {
                $rows[$index] = [
                    'product_no' => $productNo,
                    'topic' => isset($topics[$index]) ? $topics[$index] : '',
                    'stock' => isset($stocks[$index]) ? $stocks[$index] : 0,
                    'unit' => isset($units[$index]) ? $units[$index] : ''
                ];
            }

            $errors = Model::save($product, $rows, $table);
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            \Index\Log\Model::add($id, 'inventory', 'Save',
                '{LNG_Serial/Registration No.} ID : '.$id, $login->id);

            return $this->redirectResponse('reload',
                Language::get('Saved successfully'), 200, 1000);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), 400);
        }
    }
}
