<?php
/**
 * @filesource modules/inventory/models/inventory.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Inventory\Inventory;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * module=inventory-inventory
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ฟังก์ชั่นค้นหาพัสดุจาก serial
     *
     * @param Request $request
     *
     * @return JSON
     */
    public static function find(Request $request)
    {
        if ($request->initSession() && $request->isAjax() && Login::isMember()) {
            $result = static::createQuery()
                ->from('inventory V')
                ->join('category C', 'LEFT', array(array('C.type', 'unit'), array('C.category_id', 'V.unit')))
                ->where(array('V.serial', $request->post('value')->topic()))
                ->toArray()
                ->first('V.id', 'V.equipment', 'V.serial', 'C.topic unit', 'V.stock');
            if ($result) {
                echo json_encode($result);
            }
        }
    }
}
