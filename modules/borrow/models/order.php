<?php
/**
 * @filesource modules/borrow/models/order.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Order;

/**
 * ทำรายการยืม-คืนของเจ้าหน้าที่ (ต่อใบยืม 1 ใบ)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านใบยืมที่เลือก พร้อมชื่อผู้ยืม
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        return static::createQuery()
            ->select('B.*', 'U.name borrower', 'U.status borrower_status')
            ->from('borrow B')
            ->join('user U', [['U.id', 'B.borrower_id']], 'LEFT')
            ->where([['B.id', (int) $id]])
            ->first();
    }

    /**
     * อ่านรายการพัสดุในใบยืม (สำหรับหน้าเจ้าหน้าที่ และ modal รายละเอียด)
     *
     * @param int $borrow_id
     *
     * @return array
     */
    public static function items($borrow_id)
    {
        $result = static::createQuery()
            ->select(
                'S.borrow_id',
                'S.id',
                'S.num_requests',
                'S.product_no',
                'S.topic',
                'S.unit',
                'S.amount',
                'S.status',
                'I.stock',
                'V.count_stock'
            )
            ->from('borrow_items S')
            ->join('inventory_items I', [['I.product_no', 'S.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->where([['S.borrow_id', (int) $borrow_id]])
            ->orderBy('S.id')
            ->fetchAll(true);

        foreach ($result as $key => $item) {
            $result[$key]['count_stock'] = (int) $item['count_stock'];
            // ยังไม่ส่งมอบครบ ใช้ตรวจสอบปุ่มในหน้าเจ้าหน้าที่
            $result[$key]['remaining'] = (int) $item['num_requests'] - (int) $item['amount'];
        }

        return $result;
    }
}
