<?php
/**
 * @filesource modules/borrow/models/borrow.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Borrow;

use Kotchasan\Language;

/**
 * ทำรายการยืม (ฟอร์มของสมาชิก)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลใบยืมที่เลือก
     * แก้ไขได้เฉพาะใบของตัวเองที่ยังไม่มีรายการใดถูกตรวจสอบ (status > 0)
     * $id = 0 หมายถึงรายการใหม่
     *
     * @param int $id
     * @param object $login
     *
     * @return object|null
     */
    public static function get($id, $login)
    {
        $id = (int) $id;

        if ($id === 0) {
            // ใหม่
            return (object) [
                'id' => 0,
                'borrower' => $login->name,
                'borrower_id' => $login->id,
                'borrow_no' => '',
                'transaction_date' => date('Y-m-d'),
                'borrow_date' => date('Y-m-d'),
                'return_date' => null
            ];
        }

        // แก้ไข อ่านรายการที่เลือก
        return static::createQuery()
            ->select('B.*', 'U.name borrower')
            ->from('borrow B')
            ->join('user U', [['U.id', 'B.borrower_id']], 'LEFT')
            ->where([
                ['B.id', $id],
                ['B.borrower_id', $login->id]
            ])
            ->whereNotExists('borrow_items S', [['S.borrow_id', 'B.id'], ['S.status', '>', 0]])
            ->first();
    }

    /**
     * อ่านรายการพัสดุในใบยืม สำหรับตารางรายการของฟอร์ม (LineItemsManager)
     * ใบใหม่คืนค่ารายการว่าง
     *
     * @param int $borrow_id
     *
     * @return array
     */
    public static function items($borrow_id)
    {
        $borrow_id = (int) $borrow_id;
        if ($borrow_id === 0) {
            return [];
        }

        $result = static::createQuery()
            ->select('S.topic', 'S.num_requests quantity', 'S.product_no', 'S.unit', 'I.stock', 'V.count_stock')
            ->from('borrow_items S')
            ->join('inventory_items I', [['I.product_no', 'S.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->where([['S.borrow_id', $borrow_id]])
            ->orderBy('S.id')
            ->fetchAll(true);

        foreach ($result as $key => $item) {
            $unlimited = empty($item['count_stock']);
            // count_stock = 0 คือสต็อกไม่จำกัด ส่ง -1 ให้ฝั่งหน้าเว็บ
            $result[$key]['stock'] = $unlimited ? -1 : (float) $item['stock'];
            $result[$key]['stock_text'] = $unlimited
                ? Language::get('Unlimited')
                : trim(\Kotchasan\Number::format($item['stock']).' '.$item['unit']);
            unset($result[$key]['count_stock']);
        }

        return $result;
    }

    /**
     * รวบรวมรายการพัสดุที่ส่งมาจากฟอร์ม
     * เก็บเฉพาะแถวที่มีเลขครุภัณฑ์และจำนวนมากกว่า 0 รวมจำนวนของเลขครุภัณฑ์ที่ซ้ำกัน
     *
     * @param array $rows items[n][product_no|topic|quantity|unit]
     *
     * @return array
     */
    public static function parseItems(array $rows)
    {
        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $quantity = (int) ($row['quantity'] ?? 0);
            $product_no = trim((string) ($row['product_no'] ?? ''));
            if ($quantity <= 0 || $product_no === '') {
                continue;
            }
            if (isset($items[$product_no])) {
                $items[$product_no]['num_requests'] += $quantity;
                continue;
            }
            $items[$product_no] = [
                'num_requests' => $quantity,
                'topic' => trim((string) ($row['topic'] ?? '')),
                'product_no' => $product_no,
                'unit' => trim((string) ($row['unit'] ?? '')),
                'status' => 0
            ];
        }

        return $items;
    }

    /**
     * ตรวจสอบว่าเลขครุภัณฑ์ที่ส่งมายืมได้จริง (เปิดใช้งาน และมีสต็อกพอ)
     * คืนค่า array ของข้อความผิดพลาด ว่างหมายถึงผ่าน
     *
     * @param array $items ผลลัพธ์จาก parseItems()
     *
     * @return array
     */
    public static function validateItems(array $items)
    {
        if (empty($items)) {
            return [];
        }

        $rows = static::createQuery()
            ->select('I.product_no', 'I.stock', 'I.unit', 'V.topic', 'V.count_stock')
            ->from('inventory_items I')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'INNER')
            ->where([
                ['I.product_no', array_keys($items)],
                ['V.is_active', 1]
            ])
            ->fetchAll(true);

        $available = [];
        foreach ($rows as $row) {
            $available[$row['product_no']] = $row;
        }

        $errors = [];
        foreach ($items as $product_no => $item) {
            if (!isset($available[$product_no])) {
                $errors[] = Language::replace('Sorry, :name not found It&#39;s may be deleted', [':name' => $product_no]);
                continue;
            }
            $stock = $available[$product_no];
            if (empty($stock['count_stock'])) {
                // ไม่นับสต็อก ยืมได้ไม่จำกัด
                continue;
            }
            if ($item['num_requests'] > $stock['stock']) {
                $errors[] = Language::replace('There is not enough :name (remaining :stock :unit)', [
                    ':name' => $stock['topic'],
                    ':stock' => $stock['stock'],
                    ':unit' => $stock['unit']
                ]);
            }
        }

        return $errors;
    }

    /**
     * บันทึกใบยืม (insert หรือ update) พร้อมรายการพัสดุทั้งชุด (แทนที่ของเดิมทั้งหมด)
     * คืนค่า [borrow_id, errors] โดย errors เป็น array ของ field => message
     *
     * @param int $id 0 = ใหม่
     * @param array $order ข้อมูลใบยืม
     * @param array $items รายการพัสดุจาก parseItems()
     *
     * @return array
     */
    public static function save($id, array $order, array $items)
    {
        $db = \Kotchasan\DB::create();
        $id = (int) $id;
        $errors = [];

        // เลขที่ใบยืม (ใหม่ หรือไม่ได้กรอก = สร้างอัตโนมัติ)
        if ($id === 0 || $order['borrow_no'] === '') {
            $order['borrow_no'] = \Index\Number\Model::get($id, self::$cfg->borrow_no, 'borrow', 'borrow_no', self::$cfg->borrow_prefix);
        } else {
            // ตรวจสอบเลขที่ซ้ำ
            $search = $db->first('borrow', [['borrow_no', $order['borrow_no']]]);
            if ($search && $id !== (int) $search->id) {
                $errors['borrow_no'] = Language::replace('This :name already exist', [':name' => Language::get('Transaction No.')]);

                return [$id, $errors];
            }
        }

        if ($id > 0) {
            // แก้ไข
            $db->update('borrow', [['id', $id]], $order);
        } else {
            // ใหม่
            $id = $db->insert('borrow', $order);
        }

        // ลบรายการเก่าออกก่อน แล้วบันทึกรายการใหม่ทั้งชุด (สถานะรอตรวจสอบทั้งหมด)
        $db->delete('borrow_items', [['borrow_id', $id]], 0);
        $n = 0;
        foreach ($items as $save) {
            $save['id'] = $n;
            $save['borrow_id'] = $id;
            $db->insert('borrow_items', $save);
            ++$n;
        }

        return [$id, $errors];
    }
}
