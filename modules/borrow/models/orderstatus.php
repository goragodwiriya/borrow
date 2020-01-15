<?php
/**
 * @filesource modules/borrow/models/orderstatus.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Orderstatus;

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
     * @param int   $borrow_id
     * @param int   $id
     *
     * @return object|null
     */
    public static function get($borrow_id, $id)
    {
        return static::createQuery()
            ->from('borrow_items S')
            ->join('inventory I', 'INNER', array('I.id', 'S.inventory_id'))
            ->join('category C', 'LEFT', array(array('C.type', 'unit'), array('C.category_id', 'I.unit')))
            ->where(array(
                array('S.borrow_id', $borrow_id),
                array('S.id', $id),
            ))
            ->first('S.borrow_id', 'S.id', 'S.inventory_id', 'S.topic', 'S.amount', 'S.num_requests', 'S.status', 'I.stock', 'C.topic unit');
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม ยืม (order.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, member, สามารถอนุมัติได้
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_approve_borrow')) {
                $index = self::get($request->post('borrow_id')->toInt(), $request->post('id')->toInt());
                if (!$index) {
                    // ไม่พบข้อมูลที่แก้ไข
                    $ret['alert'] = Language::get('Sorry, Item not found It&#39;s may be deleted');
                } else {
                    // ค่าที่ส่งมา
                    $action = $request->post('action')->toString();
                    $amount = $request->post('amount')->toInt();
                    $status = $request->post('status')->toInt();
                    if ($action === 'status') {
                        // อัปเดตสถานะ
                        if ($status == 3 && $index->amount != 0) {
                            // คุณยังไม่ได้คืนพัสดุ กรุณาคืนพัสดุก่อน
                            $ret['alert'] = Language::get('You have not returned the equipment. Please return it first.');
                            $ret['modal'] = 'close';
                        } else {
                            $save = array('status' => $status);
                        }
                    } elseif ($action === 'delivery') {
                        if ($amount > 0) {
                            if ($amount + $index->amount > $index->num_requests) {
                                // จำนวนที่ส่งมอบมากกว่าจำนวนที่ยืม
                                $ret['ret_amount'] = Language::get('The amount delivered is greater than the amount borrowed');
                            } elseif ($index->stock > -1) {
                                if ($amount > $index->stock) {
                                    // สต๊อคไม่เพียงพอ
                                    $ret['ret_amount'] = Language::replace('There is not enough :name (remaining :stock :unit)', array(':name' => $index->topic, ':stock' => $index->stock, ':unit' => $index->unit));
                                } else {
                                    // ตัดสต๊อค
                                    $save = array('amount' => $index->amount + $amount, 'status' => $status);
                                    // อัปเดตรายการ
                                    $inventory = array('stock' => $index->stock - $amount);
                                }
                            } else {
                                // อัปเดตรายการ
                                $save = array('amount' => $index->amount + $amount, 'status' => $status);
                            }
                        }
                    } elseif ($action === 'return') {
                        if ($amount > 0) {
                            if ($index->stock > -1) {
                                if ($status == 3 && $amount != $index->amount) {
                                    // จำนวนที่คืนไม่เท่ากับจำนวนที่ส่งมอบ
                                    $ret['alert'] = Language::get('The amount returned is greater than the amount delivered');
                                } elseif ($amount > $index->amount) {
                                    // จำนวนที่คืนมากกว่าจำนวนที่ส่งมอบ
                                    $ret['alert'] = Language::get('The amount returned is greater than the amount delivered');
                                } else {
                                    // ตัดสต๊อค
                                    $save = array('amount' => $index->amount - $amount, 'status' => $status);
                                    // อัปเดตรายการ
                                    $inventory = array('stock' => $index->stock + $amount);
                                }
                            } else {
                                // อัปเดตรายการ
                                $save = array('amount' => $index->amount - $amount, 'status' => $status);
                            }
                        }
                    }
                    if (empty($ret)) {
                        if (isset($inventory)) {
                            $this->db()->update($this->getTableName('inventory'), $index->inventory_id, $inventory);
                        }
                        if (isset($save)) {
                            // บันทึก borrow_items
                            $this->db()->update($this->getTableName('borrow_items'), array(
                                array('borrow_id', $index->borrow_id),
                                array('id', $index->id),
                            ), $save);
                            // คืนค่า สถานะ
                            $ret['status_'.$index->id] = Language::find('BORROW_STATUS', null, $save['status']);
                            $ret['elem'] = 'status_'.$index->id;
                            $ret['class'] = 'term'.$save['status'];
                            if (isset($save['amount'])) {
                                $ret['amount_'.$index->id] = $save['amount'];
                            }
                        }
                        // คืนค่า
                        $ret['modal'] = 'close';
                        // เคลียร์
                        $request->removeToken();
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * รับค่าจาก action.
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = array();
        // session, referer, member, สามารถอนุมัติได้
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_approve_borrow')) {
                if (preg_match('/^(delivery|return|status)_([0-9]+)_([0-9]+)$/', $request->post('id')->toString(), $match)) {
                    $item = self::get((int) $match[2], (int) $match[3]);
                    if ($item) {
                        // คืนค่า modal
                        $ret['modal'] = \Borrow\Orderstatus\View::render($item, $match[1]);
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }
}
