<?php
/**
 * @filesource modules/borrow/models/index.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Index;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * ยืม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     * คืนค่าข้อมูล object ไม่พบคืนค่า null.
     *
     * @param int   $id
     * @param array $login
     *
     * @return object|null
     */
    public static function get($id, $login)
    {
        if (empty($id)) {
            // ใหม่
            return (object) array(
                'id' => 0,
                'borrower' => $login['name'],
                'borrower_id' => $login['id'],
                'borrow_no' => '',
                'transaction_date' => date('Y-m-d'),
                'borrow_date' => date('Y-m-d'),
                'return_date' => null,
            );
        } else {
            // แก้ไข อ่านรายการที่เลือก
            return static::createQuery()
                ->from('borrow B')
                ->where(array('B.id', $id))
                ->notExists('borrow_items', array(array('borrow_id', $id), array('status', '>', 0)))
                ->first('B.*');
        }
    }

    /**
     * อ่านรายการพัสดุในใบยืม
     * ถ้าไมมีคืนค่ารายการว่าง 1 รายการ
     *
     * @param int $borrow_id
     *
     * @return array
     */
    public static function items($borrow_id)
    {
        if ($borrow_id > 0) {
            $result = static::createQuery()
                ->select('S.borrow_id id', 'S.num_requests quantity', 'S.inventory_id', 'S.topic', 'C.topic unit', 'I.stock')
                ->from('borrow_items S')
                ->join('inventory I', 'LEFT', array(array('I.id', 'S.inventory_id')))
                ->join('category C', 'LEFT', array(array('C.type', 'unit'), array('C.category_id', 'I.unit')))
                ->where(array('S.borrow_id', $borrow_id))
                ->order('S.id')
                ->toArray()
                ->execute();
        }
        if (empty($result)) {
            // ถ้าไม่มีผลลัพท์ คืนค่ารายการเปล่าๆ 1 รายการ
            $result = array(
                0 => array(
                    'id' => 0,
                    'quantity' => 0,
                    'inventory_id' => 0,
                    'topic' => '',
                    'unit' => '',
                    'stock' => 0,
                ),
            );
        }

        return $result;

    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม ยืม (index.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, สมาชิก
        if ($request->initSession() && $request->isSafe()) {
            if ($login = Login::isMember()) {
                $order = array(
                    'borrower_id' => $login['id'],
                    'borrow_no' => $request->post('borrow_no')->topic(),
                    'transaction_date' => $request->post('transaction_date')->date(),
                    'borrow_date' => $request->post('borrow_date')->date(),
                    'return_date' => $request->post('return_date')->date(),
                );
                $borrow_id = $request->post('borrow_id')->toInt();
                // ตรวจสอบรายการที่เลือก
                $borrow = self::get($borrow_id, $login);
                if (!$borrow) {
                    // ไม่พบข้อมูลที่แก้ไข
                    $ret['alert'] = Language::get('Sorry, Item not found It&#39;s may be deleted');
                } else {
                    // ชื่อตาราง
                    $table_borrow = $this->getTableName('borrow');
                    $table_borrow_items = $this->getTableName('borrow_items');
                    // Database
                    $db = $this->db();
                    // พัสดุที่เลือก
                    $datas = array(
                        'quantity' => $request->post('quantity', array())->toInt(),
                        'topic' => $request->post('topic', array())->topic(),
                        'id' => $request->post('id', array())->toInt(),
                    );
                    $items = array();
                    foreach ($datas['quantity'] as $key => $value) {
                        if ($value > 0 && $datas['id'][$key] > 0) {
                            $items[$datas['id'][$key]] = array(
                                'num_requests' => $value,
                                'topic' => $datas['topic'][$key],
                                'inventory_id' => $datas['id'][$key],
                                'status' => 0,
                            );
                        }
                    }
                    if (empty($items)) {
                        // ไม่ได้เลือก พัสดุ
                        $ret['ret_equipment'] = 'Please fill in';
                    } else {
                        // ใหม่ หรือไม่ได้กรอก borrow_no มา
                        if ($borrow_id == 0 || $order['borrow_no'] == '') {
                            // สร้างเลข running number
                            $order['borrow_no'] = \Borrow\Number\Model::get($borrow_id, 'borrow_no', $table_borrow, 'borrow_no');
                        } else {
                            // ตรวจสอบ borrow_no ซ้ำ
                            $search = $this->db()->first($table_borrow, array(
                                array('borrow_no', $order['borrow_no']),
                            ));
                            if ($search !== false && $borrow->id != $search->id) {
                                $ret['ret_borrow_no'] = Language::replace('This :name already exist', array(':name' => Language::get('Order No.')));
                            }
                        }
                        if (empty($ret)) {
                            if ($borrow_id > 0) {
                                // แก้ไข
                                $db->update($table_borrow, $borrow_id, $order);
                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                            } else {
                                // ใหม่
                                $order['transaction_date'] = date('Y-m-d');
                                $borrow_id = $db->insert($table_borrow, $order);
                                // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                                $ret['alert'] = \Borrow\Email\Model::send($login['username'], $login['name'], $order);
                            }
                            // อ่านรายการ items เก่า (ถ้ามี)
                            foreach ($db->select($table_borrow_items, array(array('borrow_id', $borrow_id))) as $item) {
                                if (isset($items[$item['inventory_id']])) {
                                    $items[$item['inventory_id']] += $item;
                                }
                            }
                            // ลบรายการเก่าออกก่อน
                            $db->delete($table_borrow_items, array(
                                array('borrow_id', $borrow_id),
                            ), 0);
                            // save items
                            $n = 0;
                            foreach ($items as $save) {
                                $save['id'] = $n;
                                $save['borrow_id'] = $borrow_id;
                                $db->insert($table_borrow_items, $save);
                                $n++;
                            }
                            // คืนค่า
                            $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'borrow-setup', 'type' => 0, 'id' => null));
                            // เคลียร์
                            $request->removeToken();
                        }
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
}
