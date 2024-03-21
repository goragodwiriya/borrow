<?php
/**
 * @filesource modules/inventory/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Autocomplete;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * ค้นหา สำหรับ autocomplete
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค้นหา Inventory สำหรับ autocomplete
     * เฉพาะรายการที่ตัวเองรับผิดชอบ และ ที่ไม่มีผู้รับผิดชอบ
     * คืนค่าเป็น JSON
     *
     * @param Request $request
     */
    public function find(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && Login::isMember()) {
            try {
                // ข้อมูลที่ส่งมา
                if ($request->post('topic')->exists()) {
                    $search = $request->post('topic')->topic();
                    $order = 'V.topic';
                } elseif ($request->post('product_no')->exists()) {
                    $search = $request->post('product_no')->topic();
                    $order = 'I.product_no';
                }
                $where = [];
                if (isset($search)) {
                    $where[] = array($order, 'LIKE', "%$search%");
                }
                // query
                $query = $this->db()->createQuery()
                    ->select('I.inventory_id', 'V.topic', 'I.product_no')
                    ->from('inventory V')
                    ->join('inventory_items I', 'INNER', array('I.inventory_id', 'V.id'))
                    ->where($where)
                    ->limit($request->post('count', 20)->toInt())
                    ->toArray();
                if (isset($order)) {
                    $query->order($order);
                }
                $result = $query->execute();
                if (!empty($result)) {
                    // คืนค่า JSON
                    echo json_encode($result);
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }
}
