<?php
/**
 * @filesource modules/borrow/models/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Report;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-report
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
            array('S.status', $params['status'])
        );
        if (!empty($params['borrower_id'])) {
            $where[] = array('W.borrower_id', $params['borrower_id']);
        }
        $query = static::createQuery()
            ->select(Sql::CONCAT(['S.borrow_id', '_', 'S.id'], 'id'), 'W.borrow_no', 'S.product_no', 'S.topic', 'I.stock', 'S.num_requests', 'W.borrow_date',
                'W.return_date', 'U.name borrower', 'U.status Ustatus', 'W.borrower_id', 'S.amount', 'V.count_stock',
                Sql::DATEDIFF('W.return_date', date('Y-m-d'), 'due'), 'S.status')
            ->from('borrow W')
            ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
            ->join('inventory_items I', 'LEFT', array('I.product_no', 'S.product_no'))
            ->join('inventory V', 'LEFT', array('V.id', 'I.inventory_id'))
            ->join('user U', 'LEFT', array('U.id', 'W.borrower_id'))
            ->where($where);
        if ($params['status'] == 2) {
            if ($params['due'] == 1) {
                $query->andWhere(array(
                    array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '<=', 0)
                ));
            } else {
                $query->andWhere(array(
                    array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '>', 0),
                    Sql::ISNULL('W.return_date')
                ), 'OR');
            }
        }
        return $query;
    }

    /**
     * รับค่าจาก action (report.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, can_approve_borrow, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_approve_borrow')) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                // Database
                $db = $this->db();
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+)_([0-9]+),?/', $request->post('id')->filter('0-9_,'), $match)) {
                    if ($action === 'delete') {
                        // ลบ
                        foreach ($match[1] as $i => $borrow_id) {
                            $db->delete($this->getTableName('borrow_items'), array(
                                array('borrow_id', $borrow_id),
                                array('id', $match[2][$i]),
                                array('status', [0, 1])
                            ), 0);
                        }
                        // ลบรายการ borrow ที่ไม่มี borrow_items แล้ว
                        $borrows = $db->createQuery()
                            ->select('borrow_id')
                            ->from('borrow_items');
                        $db->createQuery()
                            ->delete('borrow', array('id', 'NOT IN', $borrows))
                            ->execute();
                        // log
                        \Index\Log\Model::add(0, 'borrow', 'Delete', '{LNG_Delete} {LNG_Borrow} ID : '.implode(', ', $match[1]), $login['id']);
                        // reload
                        $ret['location'] = 'reload';
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
