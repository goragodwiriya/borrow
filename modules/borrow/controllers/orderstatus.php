<?php
/**
 * @filesource modules/borrow/controllers/orderstatus.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Orderstatus;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ส่งมอบ (delivery) / รับคืน (return) / เปลี่ยนสถานะ ของรายการพัสดุในใบยืม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * การกระทำที่รองรับ และหัวข้อของ modal
     *
     * ⚠️ พารามิเตอร์ที่ส่งมากับ request ต้องชื่อ mode ห้ามใช้ action
     * เพราะ module · method · action เป็นกุญแจของ Router (Kotchasan/Router.php)
     * parseRoutes() เอา query string มาเป็นฐาน ค่าที่มากับ URL จึงชนะเส้นทางเสมอ
     * ?action=delivery บน api/borrow/orderstatus/get จะทำให้ ApiController::index()
     * ไปเรียก Controller::delivery แทน Controller::get แล้วตอบ 404 Endpoint not found
     * (Now/js/FormManager.js ก็ตัดสามคีย์นี้ทิ้งด้วยเหตุผลเดียวกัน)
     *
     * @var array
     */
    public static $modes = [
        'delivery' => '{LNG_Delivery}',
        'return' => '{LNG_Return}',
        'status' => '{LNG_Status update}'
    ];

    /**
     * GET api/borrow/orderstatus/get
     * อ่านข้อมูลรายการสำหรับเปิด modal
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
            if (!ApiController::hasPermission($login, 'can_approve_borrow')) {
                return $this->errorResponse('Permission required', 403);
            }

            $mode = $request->get('mode')->filter('a-z');
            if (!isset(self::$modes[$mode])) {
                return $this->errorResponse('Invalid action', 400);
            }

            $index = Model::get($request->get('borrow_id')->toInt(), $request->get('id')->toInt());
            if (!$index) {
                return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
            }

            $data = (array) $index;
            $data['mode'] = $mode;
            // สถานะที่เลือกไว้ล่วงหน้าตามการกระทำ (เหมือนระบบเดิม)
            $data['selected_status'] = $mode === 'delivery' ? 2 : ($mode === 'return' ? 3 : (int) $index->status);
            // จำนวนสูงสุดที่กรอกได้
            $data['max_amount'] = $mode === 'return'
                ? (int) $index->amount
                : (int) $index->num_requests - (int) $index->amount;
            $data['show_amount'] = $mode === 'status' ? 0 : 1;
            $data['stock_text'] = empty($index->count_stock)
                ? Language::get('Unlimited')
                : \Kotchasan\Number::format($index->stock).' '.$index->unit;

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'status' => \Borrow\Report\Controller::statusOptions()
                ],
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'open',
                        'template' => 'borrow/orderstatus.html',
                        'title' => Language::trans(self::$modes[$mode]).' : '.$index->topic
                    ]
                ]
            ], 'Item retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/borrow/orderstatus/save
     * บันทึกการส่งมอบ/รับคืน/เปลี่ยนสถานะ
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
            if (!ApiController::canModify($login, ['can_approve_borrow'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $mode = $request->post('mode')->filter('a-z');
            if (!isset(self::$modes[$mode])) {
                return $this->errorResponse('Invalid action', 400);
            }

            $index = Model::get($request->post('borrow_id')->toInt(), $request->post('id')->toInt());
            if (!$index) {
                return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
            }

            // ตรวจสอบและคำนวณผลลัพธ์
            list($save, $stock, $errors, $alert) = Model::submit(
                $mode,
                $request->post('amount')->toInt(),
                $request->post('status')->toInt(),
                $index
            );

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }
            if ($alert !== '') {
                // ข้อผิดพลาดระดับฟอร์ม ปิด modal พร้อมแจ้งเตือน (เหมือนระบบเดิม)
                return $this->errorResponse($alert, 400);
            }
            if ($save === null) {
                return $this->errorResponse('Unable to complete the transaction', 400);
            }

            Model::save($index, $save, $stock);
            \Index\Log\Model::add(
                $index->borrow_id,
                'borrow',
                'Status',
                $index->topic.' : '.Language::get('BORROW_STATUS', null, $save['status']),
                $login->id
            );

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
