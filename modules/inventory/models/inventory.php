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
 * โมเดลสำหรับ (vehicles.php).
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ฟังก์ชั่นค้นหาพัสดุจาก เลขพัสดุ
     *
     * @param Request $request
     *
     * @return JSON
     */
    public static function find(Request $request)
    {
        if ($request->initSession() && $request->isAjax() && Login::isMember()) {
            $result = static::createQuery()
                ->from('inventory I')
                ->join('category C', 'LEFT', array(array('C.type', 'units'), array('C.category_id', 'I.unit')))
                ->where(array('I.serial', $request->post('value')->topic()))
                ->toArray()
                ->first('I.id', 'I.equipment', 'I.serial', 'C.topic unit', 'I.stock');
            if ($result) {
                echo json_encode($result);
            }
        }
    }
}
