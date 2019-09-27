<?php
/**
 * @filesource modules/borrow/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Setup;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable.
     *
     * @param object $index
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($index)
    {
        $where = array(
            array('S.status', $index->status),
            array('W.borrower_id', $index->borrower_id),
        );
        if (!empty($index->borrow_id)) {
            $where[] = array('S.borrow_id', $index->borrow_id);
        }
        if (!empty($index->inventory_id)) {
            $where[] = array('S.inventory_id', $index->inventory_id);
        }
        if ($index->status == 2) {
            if ($index->due == 1) {
                $where[] = array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '<=', 0);
            } else {
                $where[] = Sql::create('(W.`return_date` IS NULL OR DATEDIFF(W.`return_date`, "'.date('Y-m-d').'") > 0)');
            }
        }
        $q1 = static::createQuery()
            ->select('borrow_id', Sql::COUNT('id', 'count'))
            ->from('borrow_items')
            ->where(array('status', '>', 0))
            ->groupBy('borrow_id');

        return static::createQuery()
            ->select('S.borrow_id', 'S.id', 'W.borrow_no', 'S.inventory_id', 'S.topic', 'S.num_requests', 'W.borrow_date',
                'W.return_date', 'S.amount', 'Q1.count', Sql::DATEDIFF('W.return_date', date('Y-m-d'), 'due'), 'S.status')
            ->from('borrow W')
            ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
            ->join(array($q1, 'Q1'), 'LEFT', array('Q1.borrow_id', 'W.id'))
            ->where($where);
    }

    /**
     * รับค่าจาก action (setup.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = array();
        // session, referer, member, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::notDemoMode($login)) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                $borrow_id = $request->post('id')->toInt();
                $id = $request->post('opt')->toInt();
                if ($action == 'detail') {
                    // แสดงรายละเอียด (modal)
                    $borrow = $this->db()->createQuery()
                        ->from('borrow B')
                        ->join('user U', 'LEFT', array('U.id', 'B.borrower_id'))
                        ->where(array('B.id', $borrow_id))
                        ->first('B.*', 'U.name borrower', 'U.status');
                    if ($borrow) {
                        // คืนค่า modal
                        $ret['modal'] = \Borrow\Detail\View::render($borrow);
                    }
                } elseif ($action == 'delete') {
                    // ลบ
                    $search = $this->db()->createQuery()
                        ->from('borrow W')
                        ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
                        ->where(array(
                            array('S.borrow_id', $borrow_id),
                            array('S.id', $id),
                            array('S.status', array(0, 1)),
                            array('W.borrower_id', $login['id']),
                        ))
                        ->first('S.borrow_id', 'S.id');
                    if ($search) {
                        // ลบรายการ
                        $this->db()->delete($this->getTableName('borrow_items'), array(
                            array('borrow_id', $search->borrow_id),
                            array('id', $search->id),
                        ));
                        // ลบรายการ borrow ที่ไม่มี borrow_items แล้ว
                        $this->db()->createQuery()
                            ->delete('borrow', array('id', $search->borrow_id))
                            ->notExists('borrow_items', array('borrow_id', $search->borrow_id))
                            ->execute();
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
