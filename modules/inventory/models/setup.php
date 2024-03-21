<?php
/**
 * @filesource modules/inventory/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Setup;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-setup
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
        $where = [];
        $select = ['V.id', 'V.topic', 'I.product_no'];
        foreach (Language::get('INVENTORY_CATEGORIES') as $key => $label) {
            $select[] = "V.`$key` AS `$label`";
            if (!empty($params[$key])) {
                $where[] = array('V.'.$key, $params[$key]);
            }
        }
        $select[] = 'I.stock';
        $select[] = 'I.unit';
        $select[] = 'V.inuse';
        return static::createQuery()
            ->select($select)
            ->from('inventory V')
            ->join('inventory_items I', 'LEFT', array('I.inventory_id', 'V.id'))
            ->where($where);
    }

    /**
     * รับค่าจาก action (setup.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, can_manage_inventory, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_inventory')) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                // Database
                $db = $this->db();
                // table
                $table = $this->getTableName('inventory');
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->filter('0-9,'), $match)) {
                    if ($action === 'delete') {
                        // ลบ
                        $db->delete($table, array('id', $match[1]), 0);
                        $db->delete($this->getTableName('inventory_meta'), array('inventory_id', $match[1]), 0);
                        $db->delete($this->getTableName('inventory_items'), array('inventory_id', $match[1]), 0);
                        // ลบรูปภาพ
                        $dir = ROOT_PATH.DATA_FOLDER.'inventory/';
                        foreach ($match[1] as $id) {
                            if (is_file($dir.$id.'.jpg')) {
                                unlink($dir.$id.'.jpg');
                            }
                        }
                        // log
                        \Index\Log\Model::add(0, 'inventory', 'Delete', '{LNG_Delete} {LNG_Inventory} ID : '.implode(', ', $match[1]), $login['id']);
                        // reload
                        $ret['location'] = 'reload';
                    } elseif ($action == 'inuse') {
                        // สถานะ
                        $search = $db->first($table, (int) $match[1][0]);
                        if ($search) {
                            $status = $search->inuse == 1 ? 0 : 1;
                            $db->update($table, $search->id, array('inuse' => $status));
                            // คืนค่า
                            $ret['elem'] = 'status_'.$search->id;
                            $ret['title'] = Language::get('INVENTORY_STATUS', '', $status);
                            $ret['class'] = 'icon-valid '.($status == '1' ? 'access' : 'disabled');
                            // log
                            \Index\Log\Model::add($search->id, 'inventory', 'Status', $ret['title'].' ID : '.$search->id, $login['id']);
                        }
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
