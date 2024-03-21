<?php
/**
 * @filesource modules/borrow/models/order.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Order;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-order
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล object ไม่พบคืนค่า null.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        return static::createQuery()
            ->from('borrow B')
            ->join('user U', 'LEFT', array('U.id', 'B.borrower_id'))
            ->where(array('B.id', $id))
            ->first('B.*', 'U.name borrower');
    }

    /**
     * อ่านรายการพัสดุในใบยืม
     *
     * @param int    $borrow_id
     *
     * @return array
     */
    public static function items($borrow_id)
    {
        return static::createQuery()
            ->select('S.borrow_id', 'S.id', 'S.num_requests', 'S.product_no', 'S.topic', 'S.unit',
                'S.amount', 'S.status', 'I.stock')
            ->from('borrow_items S')
            ->join('inventory_items I', 'INNER', array('I.product_no', 'S.product_no'))
            ->where(array('S.borrow_id', $borrow_id))
            ->order('S.id')
            ->toArray()
            ->execute();
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (order.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, สมาชิก, สามารถอนุมัติได้
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_approve_borrow')) {
                try {
                    $order = array(
                        'borrower_id' => $request->post('borrower_id')->toInt(),
                        'borrow_no' => $request->post('borrow_no')->topic(),
                        'transaction_date' => $request->post('transaction_date')->date(),
                        'borrow_date' => $request->post('borrow_date')->date(),
                        'return_date' => $request->post('return_date')->date()
                    );
                    // ตรวจสอบรายการที่เลือก
                    $borrow = self::get($request->post('borrow_id')->toInt());
                    if ($borrow) {
                        if ($order['borrower_id'] == 0) {
                            // ไม่ได้กรอก borrower
                            $ret['ret_borrower'] = 'this';
                        }
                        // ชื่อตาราง
                        $table_borrow = $this->getTableName('borrow');
                        // Database
                        $db = $this->db();
                        // save borrow_no
                        if ($order['borrow_no'] == '') {
                            // สร้างเลข running number
                            $order['borrow_no'] = \Index\Number\Model::get($borrow->id, 'borrow_no', $table_borrow, 'borrow_no', self::$cfg->borrow_prefix);
                        } else {
                            // ตรวจสอบ borrow_no ซ้ำ
                            $search = $this->db()->first($table_borrow, array(
                                array('borrow_no', $order['borrow_no'])
                            ));
                            if ($search !== false && $borrow->id != $search->id) {
                                $ret['ret_borrow_no'] = Language::replace('This :name already exist', array(':name' => Language::get('Order No.')));
                            }
                        }
                        if (empty($ret)) {
                            // อัปเดต borrow
                            $db->update($table_borrow, $borrow->id, $order);
                            // log
                            \Index\Log\Model::add($borrow->id, 'borrow', 'Save', '{LNG_Transaction details} {LNG_Borrow} &amp; {LNG_Return}', $login['id'], $order);
                            if ($request->post('send_mail')->toBoolean()) {
                                // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                                $order['id'] = $borrow->id;
                                $ret['alert'] = \Borrow\Email\Model::send($order);
                            } else {
                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                            }
                            $ret['location'] = $request->getUri()->postBack('index.php', array('id' => null));
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
