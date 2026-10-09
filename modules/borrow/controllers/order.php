<?php
/**
 * @filesource modules/borrow/controllers/order.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Order;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API หน้าตรวจสอบใบยืมของเจ้าหน้าที่
 * (แก้ไขหัวใบยืม + รายการพัสดุพร้อมปุ่มส่งมอบ/คืน/เปลี่ยนสถานะ)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/borrow/order/get
     * อ่านข้อมูลใบยืมสำหรับฟอร์มของเจ้าหน้าที่
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

            $id = $request->get('id', 0)->toInt();
            $index = Model::get($id);
            if ($id === 0 || !$index) {
                return $this->redirectResponse('/borrow-report', 'Sorry, Item not found It&#39;s may be deleted', 404);
            }

            $data = (array) $index;
            $data['items'] = \Borrow\Report\Controller::detailData($index)['items'];
            $data['send_mail'] = self::$cfg->noreply_email === '' ? 0 : 1;

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'status' => \Borrow\Report\Controller::statusOptions()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/borrow/order/save
     * บันทึกหัวใบยืม (เจ้าหน้าที่เปลี่ยนผู้ยืม/เลขที่/วันที่ได้)
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

            $id = $request->post('id', 0)->toInt();
            $borrow = Model::get($id);
            if ($id === 0 || !$borrow) {
                return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
            }

            $order = [
                'borrower_id' => $request->post('borrower_id')->toInt(),
                'borrow_no' => $request->post('borrow_no')->topic(),
                'transaction_date' => $request->post('transaction_date')->date(),
                'borrow_date' => $request->post('borrow_date')->date(),
                'return_date' => $request->post('return_date')->date()
            ];
            if ($order['return_date'] === '') {
                $order['return_date'] = null;
            }

            $db = \Kotchasan\DB::create();
            $errors = [];

            if ($order['borrower_id'] === 0) {
                $errors['borrower_id'] = 'Please fill in';
            } elseif (!$db->first('user', [['id', $order['borrower_id']]])) {
                $errors['borrower_id'] = Language::replace('Sorry, :name not found It&#39;s may be deleted', [':name' => Language::get('Borrower')]);
            }
            if ($order['borrow_date'] === '') {
                $errors['borrow_date'] = 'Please fill in';
            }
            if ($order['transaction_date'] === '') {
                $order['transaction_date'] = $borrow->transaction_date;
            }
            if ($order['return_date'] !== null && $order['return_date'] < $order['borrow_date']) {
                $errors['return_date'] = Language::get('Date of return must be after the borrowed date');
            }

            if ($order['borrow_no'] === '') {
                // สร้างเลขที่อัตโนมัติ
                $order['borrow_no'] = \Index\Number\Model::get($id, self::$cfg->borrow_no, 'borrow', 'borrow_no', self::$cfg->borrow_prefix);
            } else {
                $search = $db->first('borrow', [['borrow_no', $order['borrow_no']]]);
                if ($search && $id !== (int) $search->id) {
                    $errors['borrow_no'] = Language::replace('This :name already exist', [':name' => Language::get('Transaction No.')]);
                }
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $db->update('borrow', [['id', $id]], $order);
            \Index\Log\Model::add($id, 'borrow', 'Save', 'Saved borrow : '.$order['borrow_no'], $login->id);

            if ($request->post('send_mail')->toBoolean()) {
                // แจ้งผู้ที่เกี่ยวข้อง
                $order['id'] = $id;
                \Borrow\Email\Model::send($order);
            }

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
