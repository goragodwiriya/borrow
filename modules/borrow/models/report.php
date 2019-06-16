<?php
/**
 * @filesource modules/borrow/models/report.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Report;

use Kotchasan\Database\Sql;

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
     * Query ข้อมูลสำหรับส่งให้กับ DataTable.
     *
     * @param array $params
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('S.status', $params['status']),
        );
        if (!empty($params['borrow_id'])) {
            $where[] = array('S.borrow_id', $params['borrow_id']);
        }
        if (!empty($params['inventory_id'])) {
            $where[] = array('S.inventory_id', $params['inventory_id']);
        }
        if (!empty($params['borrower_id'])) {
            $where[] = array('W.borrower_id', $params['borrower_id']);
        }
        $query = static::createQuery()
            ->select('S.borrow_id', 'S.id', 'W.borrow_no', 'S.inventory_id', 'S.topic', 'I.stock', 'S.num_requests', 'W.borrow_date',
                'W.return_date', 'U.name borrower', 'U.status Ustatus', 'W.borrower_id', 'S.amount',
                Sql::DATEDIFF('W.return_date', date('Y-m-d'), 'due'), 'S.status')
            ->from('borrow W')
            ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
            ->join('inventory I', 'INNER', array('I.id', 'S.inventory_id'))
            ->join('user U', 'LEFT', array('U.id', 'W.borrower_id'))
            ->where($where);
        if ($params['status'] == 2) {
            if ($params['due'] == 1) {
                $query->andWhere(array(
                    array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '<=', 0),
                ));
            } else {
                $query->andWhere(array(
                    array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '>', 0),
                    Sql::ISNULL('W.return_date'),
                ), 'OR');
            }
        }

        return $query;
    }
}
