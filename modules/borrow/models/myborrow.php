<?php
/**
 * @filesource modules/borrow/models/borrow.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Myborrow;

/**
 * การลบรายการยืม-คืน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ลบรายการที่เลือก (เฉพาะสถานะ 0=รอตรวจสอบ, 1=ไม่อนุมัติ)
     * แล้วเก็บกวาดใบยืมที่ไม่เหลือรายการใดๆ
     *
     * @param array $items ผลลัพธ์จาก \Borrow\Myborrow\Controller::parseRowIds()
     * @param int|null $borrower_id ถ้าระบุจะลบได้เฉพาะใบของสมาชิกคนนี้
     *
     * @return int จำนวนรายการที่ลบ
     */
    public static function remove(array $items, $borrower_id = null)
    {
        if (empty($items)) {
            return 0;
        }

        $db = \Kotchasan\DB::create();
        $count = 0;

        foreach ($items as $item) {
            if ($borrower_id !== null) {
                // ตรวจสอบความเป็นเจ้าของก่อนลบ
                $borrow = $db->first('borrow', [
                    ['id', $item['borrow_id']],
                    ['borrower_id', (int) $borrower_id]
                ]);
                if (!$borrow) {
                    continue;
                }
            }
            $count += $db->delete('borrow_items', [
                ['borrow_id', $item['borrow_id']],
                ['id', $item['item_id']],
                ['status', [0, 1]]
            ], 1);
        }

        if ($count > 0) {
            // ลบใบยืมที่ไม่เหลือรายการพัสดุแล้ว
            $remaining = static::createQuery()
                ->select('borrow_id')
                ->from('borrow_items');
            static::createQuery()
                ->delete('borrow')
                ->where([['id', 'NOT IN', $remaining]])
                ->execute();
        }

        return $count;
    }
}
