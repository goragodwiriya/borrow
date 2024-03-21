<?php
/**
 * @filesource modules/borrow/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Autocomplete;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;

/**
 * autocomplete
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค้นหาสินค้า สำหรับ autocomplete
     * คืนค่าเป็น JSON
     *
     * @param Request $request
     */
    public function findInventory(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && Login::isMember()) {
            try {
                // ข้อมูลที่ส่งมา
                $search = $request->post('inventory')->topic();
                if ($search != '') {
                    $where = array(
                        array('V.inuse', 1)
                    );
                    if ($search != '') {
                        $where[] = Sql::create("(V.`topic` LIKE '%$search%' OR I.`product_no` LIKE '$search%')");
                    }
                    $result = $this->db()->createQuery()
                        ->select('V.id', 'V.topic', 'I.product_no', 'I.unit', 'I.stock')
                        ->from('inventory V')
                        ->join('inventory_items I', 'INNER', array('I.inventory_id', 'V.id'))
                        ->where($where)
                        ->andWhere(array(
                            array('I.stock', '>', 0),
                            array('V.count_stock', 0)
                        ), 'OR')
                        ->order('V.topic', 'I.product_no')
                        ->limit($request->post('count')->toInt())
                        ->cacheOn()
                        ->toArray()
                        ->execute();
                    // คืนค่า JSON
                    echo json_encode($result);
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }
}
