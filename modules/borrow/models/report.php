<?php
/**
 * @filesource modules/borrow/models/report.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Report;

use Kotchasan\Database\Sql;

/**
 * รายการยืม-คืน (1 แถวคือ 1 รายการพัสดุในใบยืม)
 * ใช้ร่วมกันทั้งตาราง "การยืมของฉัน" และ "รายงานการยืม-คืน"
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับ DataTable
     *
     * @param array $params [status, due, borrower_id, search]
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $today = date('Y-m-d');
        $where = [
            ['S.status', $params['status']]
        ];
        if (!empty($params['borrower_id'])) {
            $where[] = ['W.borrower_id', $params['borrower_id']];
        }
        if ((int) $params['status'] === 2) {
            if (!empty($params['due'])) {
                // ครบกำหนดคืนแล้ว
                $where[] = [Sql::DATEDIFF('W.return_date', $today), '<=', 0];
            } else {
                // ยังไม่ครบกำหนดคืน (รวมรายการที่ไม่ระบุกำหนดคืน)
                $where[] = Sql::create('(W.`return_date` IS NULL OR DATEDIFF(W.`return_date`, "'.$today.'") > 0)');
            }
        }

        // จำนวนรายการที่ถูกตรวจสอบแล้วในใบเดียวกัน (ถ้ามี = แก้ไขใบยืมไม่ได้)
        $checked = static::createQuery()
            ->select('borrow_id', Sql::COUNT('id', 'count'))
            ->from('borrow_items')
            ->where([['status', '>', 0]])
            ->groupBy('borrow_id');

        $query = static::createQuery()
            ->select(
                Sql::CONCAT(['S.borrow_id', 'S.id'], 'id', '_'),
                'S.borrow_id',
                'S.id item_id',
                'W.borrow_no',
                'W.borrow_date',
                'W.return_date',
                'W.borrower_id',
                'U.name borrower',
                'S.product_no',
                'S.topic',
                'S.unit',
                'S.num_requests',
                'S.amount',
                'S.status',
                'I.stock',
                'V.count_stock',
                'Q1.count checked',
                Sql::DATEDIFF('W.return_date', $today, 'due')
            )
            ->from('borrow W')
            ->join('borrow_items S', [['S.borrow_id', 'W.id']], 'INNER')
            ->join('inventory_items I', [['I.product_no', 'S.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->join('user U', [['U.id', 'W.borrower_id']], 'LEFT')
            ->join([$checked, 'Q1'], [['Q1.borrow_id', 'W.id']], 'LEFT')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['W.borrow_no', 'LIKE', $search],
                ['S.topic', 'LIKE', $search],
                ['S.product_no', 'LIKE', $search],
                ['U.name', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }
}
