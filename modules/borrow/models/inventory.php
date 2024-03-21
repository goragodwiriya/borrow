<?php
/**
 * @filesource modules/borrow/models/inventory.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Gcms\Login;
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
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('V.inuse', 1)
        );
        if ($params['category_id'] > 0) {
            $where[] = array('V.category_id', $params['category_id']);
        }
        if ($params['model_id'] > 0) {
            $where[] = array('V.model_id', $params['model_id']);
        }
        if ($params['type_id'] > 0) {
            $where[] = array('V.type_id', $params['type_id']);
        }
        return static::createQuery()
            ->select('V.id', 'V.topic', 'I.product_no', 'V.category_id', 'V.type_id', 'V.model_id', 'I.stock', 'I.unit')
            ->from('inventory V')
            ->join('inventory_items I', 'LEFT', array('I.inventory_id', 'V.id'))
            ->where($where);
    }

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
                ->from('inventory V')
                ->join('inventory_items I', 'INNER', array('I.inventory_id', 'V.id'))
                ->where(array(
                    array('I.product_no', $request->post('value')->topic()),
                    array('V.inuse', 1)
                ))
                ->andWhere(array(
                    array('I.stock', '>', 0),
                    array('V.count_stock', 0)
                ), 'OR')
                ->order('V.topic', 'I.product_no')
                ->toArray()
                ->first('V.id', 'V.topic', 'I.product_no', 'SQL(IFNULL(I.`unit`,"") `unit`)', 'I.stock');
            if ($result) {
                // คืนค่า JSON
                echo json_encode($result);
            }
        }
    }

    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param string $product_no
     *
     * @return object|null
     */
    public static function get($product_no)
    {
        $query = static::createQuery()
            ->from('inventory V')
            ->join('inventory_items I', 'INNER', array('I.inventory_id', 'V.id'))
            ->where(array('I.product_no', $product_no))
            ->cacheOn();
        $select = array('V.*', 'I.product_no', 'I.unit', 'I.stock');
        $n = 0;
        foreach (Language::get('INVENTORY_METAS', []) as $key => $label) {
            $query->join('inventory_meta M'.$n, 'LEFT', array(array('M'.$n.'.inventory_id', 'V.id'), array('M'.$n.'.name', $key)));
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }
        return $query->first($select);

    }

    /**
     * รับค่าจาก action (inventory.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, Ajax
        if ($request->initSession() && $request->isReferer() && $request->isAjax()) {
            $action = $request->post('action')->toString();
            if (preg_match('/^detail_(.*)$/', $action, $match)) {
                // แสดงรายละเอียด
                $search = self::get($match[1]);
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
