<?php
/**
 * @filesource modules/borrow/models/home.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Home;

use Kotchasan\Database\Sql;

/**
 * สถิติสำหรับการ์ดสรุปของโมดูลยืม-คืน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * นับจำนวนรายการตามสถานะ
     * pending = รอตรวจสอบ (ของฉัน), confirmed = อนุมัติและยังไม่ครบกำหนดคืน,
     * returned = อนุมัติและครบกำหนดคืนแล้ว, allpending = รอตรวจสอบทั้งหมด (เฉพาะผู้อนุมัติ)
     *
     * @param object $login
     * @param bool $can_approve
     *
     * @return array
     */
    public static function get($login, $can_approve)
    {
        $today = date('Y-m-d');

        $datas = [
            // รอตรวจสอบ (ของฉัน)
            'pending' => self::count([
                ['W.borrower_id', $login->id],
                ['S.status', 0]
            ]),
            // ครบกำหนดคืนแล้ว (ของฉัน)
            'returned' => self::count([
                ['W.borrower_id', $login->id],
                ['S.status', 2],
                [Sql::DATEDIFF('W.return_date', $today), '<=', 0]
            ]),
            // อนุมัติแล้ว ยังไม่ครบกำหนดคืน (ของฉัน)
            'confirmed' => self::count([
                ['W.borrower_id', $login->id],
                ['S.status', 2],
                Sql::create('(W.`return_date` IS NULL OR DATEDIFF(W.`return_date`, "'.$today.'") > 0)')
            ])
        ];

        if ($can_approve) {
            // รายการรอตรวจสอบทั้งหมด
            $datas['allpending'] = self::count([['S.status', 0]]);
        }

        return $datas;
    }

    /**
     * นับจำนวนรายการพัสดุที่ยืมตามเงื่อนไขที่กำหนด
     *
     * @param array $where
     *
     * @return int
     */
    protected static function count(array $where)
    {
        $result = static::createQuery()
            ->selectCount()
            ->from('borrow W')
            ->join('borrow_items S', [['S.borrow_id', 'W.id']], 'INNER')
            ->where($where)
            ->first();

        return $result ? (int) $result->count : 0;
    }
}
