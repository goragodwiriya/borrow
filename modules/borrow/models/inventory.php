<?php
/**
 * @filesource modules/borrow/models/inventory.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-inventory
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
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        return static::createQuery()
            ->select()
            ->from('inventory')
            ->where(array('status', 1))
            ->andWhere(array(
                array('stock', '>', 0),
                array('stock', -1),
            ), 'OR');
    }

    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int  $id     ID
     *
     * @return object|null
     */
    public static function get($id)
    {
        $query = static::createQuery()
            ->from('inventory I')
            ->join('category C', 'LEFT', array(array('C.type', 'unit'), array('C.category_id', 'I.unit')))
            ->where(array('I.id', $id));
        $select = array('I.id', 'I.equipment', 'I.serial', 'I.detail', 'I.stock', 'C.topic unit');
        $n = 1;
        foreach (Language::get('INVENTORY_CATEGORIES') as $key => $label) {
            $query->join('category C'.$n, 'LEFT', array(array('C'.$n.'.type', $key), array('C'.$n.'.category_id', 'I.'.$key)));
            $select[] = 'C'.$n.'.topic '.$key;
            $n++;
        }

        return $query->first($select);
    }

    /**
     * รับค่าจาก action.
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = array();
        // session, referer, Ajax
        if ($request->initSession() && $request->isReferer() && $request->isAjax()) {
            $action = $request->post('action')->toString();
            if ($action === 'detail') {
                // แสดงรายละเอียด
                $search = self::get($request->post('id')->toInt());
                if ($search) {
                    $ret['modal'] = \Borrow\Detail\View::details($search);
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
