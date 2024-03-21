<?php
/**
 * @filesource modules/inventory/models/items.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Items;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-write&tab=price
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param object $product
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($product)
    {
        $select = array('I.product_no barcode', 'I.product_no', 'I.stock', 'I.unit');
        $query = static::createQuery()
            ->select($select)
            ->from('inventory_items I')
            ->where(array('I.inventory_id', $product->id))
            ->order('I.product_no')
            ->toArray();
        $result = $query->execute();
        if (empty($result)) {
            $result = array(
                array(
                    'barcode' => '',
                    'product_no' => '',
                    'stock' => 1,
                    'unit' => ''
                )
            );
        }
        return $result;
    }

    /**
     * บันทึกข้อมูล (items.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_manage_inventory, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_manage_inventory') && Login::notDemoMode($login)) {
                try {
                    // อ่านข้อมูลที่เลือก
                    $index = \Inventory\Write\Model::get($request->post('inventory_id')->toInt());
                    if ($index) {
                        // ตาราง
                        $table_items = $this->getTableName('inventory_items');
                        // Database
                        $db = $this->db();
                        // รับค่าจากการ POST
                        $stock = $request->post('stock', [])->toFloat();
                        $unit = $request->post('unit', [])->topic();
                        $items = [];
                        foreach ($request->post('product_no', [])->topic() as $k => $product_no) {
                            if ($product_no != '') {
                                if (isset($items[$product_no])) {
                                    // product_no ซ้ำ
                                    $ret['ret_product_no_'.$k] = Language::replace('This :name already exist', array(':name' => Language::get('Product code')));
                                } else {
                                    // ตรวจสอบ product_no ซ้ำ (DB)
                                    $search = $db->first($table_items, array('product_no', $product_no));
                                    if ($search && $search->inventory_id != $index->id) {
                                        // product_no ซ้ำ
                                        $ret['ret_product_no_'.$k] = Language::replace('This :name already exist', array(':name' => Language::get('Product code')));
                                    } else {
                                        $items[$product_no] = array(
                                            'product_no' => $product_no,
                                            'inventory_id' => $index->id,
                                            'stock' => $stock[$k],
                                            'unit' => isset($unit[$k]) ? $unit[$k] : null
                                        );
                                    }
                                }
                            }
                        }
                        if (empty($ret)) {
                            // ลบข้อมูลเก่า ที่ยังไม่ได้ขาย
                            $where = array(
                                array('inventory_id', $index->id)
                            );
                            $db->delete($table_items, $where, 0);
                            // เพิ่มรายการใหม่
                            foreach ($items as $item) {
                                $db->insert($table_items, $item);
                            }
                            // log
                            \Index\Log\Model::add($index->id, 'inventory', 'Save', '{LNG_Serial/Registration No.} ID : '.$index->id, $login['id']);
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            $ret['location'] = 'reload';
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
