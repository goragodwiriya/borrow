<?php
/**
 * @filesource modules/borrow/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
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
            $search = $request->post('equipment')->topic();
            $where = array(
                array('I.status', 1),
            );
            if ($search != '') {
                $where[] = Sql::create("(I.`equipment` LIKE '%$search%' OR I.`serial` LIKE '$search%')");
            }
            $result = $this->db()->createQuery()
                ->select('I.id', 'I.equipment', 'I.serial', 'C.topic unit', 'I.stock')
                ->from('inventory I')
                ->join('category C', 'LEFT', array(array('C.type', 'unit'), array('C.category_id', 'I.unit')))
                ->where($where)
                ->andWhere(array(
                    array('I.stock', '>', 0),
                    array('I.stock', -1),
                ), 'OR')
                ->order('I.equipment', 'I.serial')
                ->limit($request->post('count')->toInt())
                ->cacheOn()
                ->toArray()
                ->execute();
            if (!empty($result)) {
                // คืนค่า JSON
                echo json_encode($result);
            }

        }
    }
}
