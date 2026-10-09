<?php
/**
 * @filesource modules/borrow/controllers/borrow.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Borrow;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ฟอร์มทำรายการยืมของสมาชิก
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/borrow/borrow/get
     * อ่านข้อมูลใบยืมสำหรับฟอร์ม
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $id = $request->get('id', 0)->toInt();
            $index = Model::get($id, $login);
            if ($index === null) {
                return $this->redirectResponse('/myborrow', 'Sorry, Item not found It&#39;s may be deleted', 404);
            }

            $data = (array) $index;
            // รายการพัสดุในใบยืม (ตารางรายการของ LineItemsManager อ่านผ่าน data-attr="data:items")
            $data['items'] = Model::items($index->id);

            // เข้ามาจากปุ่มยืมในหน้ารายการพัสดุ (/borrow?product_no=...)
            // ใส่พัสดุตัวนั้นลงตารางให้เลย ผู้ใช้จะได้ไม่ต้องค้นหาซ้ำอีกรอบ
            //
            // ⚠️ ห้ามตอบกลับเป็น actions (notification/redirect) ในเมธอดนี้
            // FormManager จะหยุดทันทีที่เจอ actions แล้วไม่เติมข้อมูลลงฟอร์มเลย
            // เลขครุภัณฑ์ที่ยืมไม่ได้จึงได้แค่ฟอร์มเปล่า ไม่ใช่ข้อความผิดพลาด
            // (ปุ่มยืมในตารางซ่อนตัวที่ของหมดอยู่แล้ว)
            $product_no = $request->get('product_no')->topic();
            if ($product_no !== '') {
                $exists = false;
                foreach ($data['items'] as $item) {
                    if ((string) $item['product_no'] === $product_no) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $product = \Borrow\Inventory\Model::find($product_no);
                    if ($product !== null) {
                        $product['quantity'] = 1;
                        $data['items'][] = $product;
                    }
                }
            }

            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/borrow/borrow/save
     * บันทึกใบยืม
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::isNotDemoMode($login)) {
                return $this->errorResponse('Failed to process request', 403);
            }

            $id = $request->post('id', 0)->toInt();
            // แก้ไขได้เฉพาะใบของตัวเองที่ยังไม่มีรายการใดถูกตรวจสอบ
            $borrow = Model::get($id, $login);
            if ($borrow === null) {
                return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
            }

            $order = [
                'borrower_id' => $login->id,
                'borrow_no' => $request->post('borrow_no')->topic(),
                'transaction_date' => $id === 0 ? date('Y-m-d') : $request->post('transaction_date')->date(),
                'borrow_date' => $request->post('borrow_date')->date(),
                'return_date' => $request->post('return_date')->date()
            ];
            if ($order['return_date'] === '') {
                $order['return_date'] = null;
            }

            $items = Model::parseItems($request->post('items', [])->toArray());

            $errors = [];
            if ($order['borrow_date'] === '') {
                $errors['borrow_date'] = 'Please fill in';
            }
            if ($order['transaction_date'] === '') {
                $order['transaction_date'] = date('Y-m-d');
            }
            if ($order['return_date'] !== null && $order['return_date'] < $order['borrow_date']) {
                $errors['return_date'] = Language::get('Date of return must be after the borrowed date');
            }
            if (empty($items)) {
                // ยังไม่ได้เลือกพัสดุ
                $errors['items'] = 'Please fill in';
            } else {
                $stockErrors = Model::validateItems($items);
                if (!empty($stockErrors)) {
                    $errors['items'] = implode(' ', $stockErrors);
                }
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            list($borrow_id, $errors) = Model::save($id, $order, $items);
            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $order['id'] = $borrow_id;
            if ($id === 0) {
                // แจ้งผู้ที่เกี่ยวข้อง เฉพาะตอนสร้างใบใหม่ (เหมือนระบบเดิม)
                \Borrow\Email\Model::send($order);
                \Index\Log\Model::add($borrow_id, 'borrow', 'Save', 'Add borrow : '.$order['borrow_no'], $login->id);
            } else {
                \Index\Log\Model::add($borrow_id, 'borrow', 'Save', 'Edit borrow : '.$order['borrow_no'], $login->id);
            }

            return $this->redirectResponse('/myborrow', 'Saved successfully', 200, 1000);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
