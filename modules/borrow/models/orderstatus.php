<?php
/**
 * @filesource modules/borrow/models/orderstatus.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Orderstatus;

/**
 * ส่งมอบ (Delivery) / คืน (Return) / เปลี่ยนสถานะ ต่อรายการพัสดุ
 * พร้อมตัด/คืนสต็อกตามกติกาเดิม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านรายการที่เลือก (ต้องมีพัสดุตรงกันในคลังจริง)
     *
     * @param int $borrow_id
     * @param int $id        ลำดับรายการในใบ
     *
     * @return object|null
     */
    public static function get($borrow_id, $id)
    {
        return static::createQuery()
            // ต้องมี inventory_id / inventory_item_id ติดมาด้วย เพราะการเดินสต๊อก
            // ทุกเม็ดต้องผ่าน Posting API ซึ่งอ้างสินค้าด้วย id ไม่ใช่ product_no
            ->select('S.borrow_id', 'S.id', 'S.product_no', 'S.topic', 'S.amount', 'S.num_requests', 'S.status', 'I.stock', 'S.unit', 'V.count_stock')
            ->selectRaw('I.`id` AS `inventory_item_id`')
            ->selectRaw('I.`inventory_id` AS `inventory_id`')
            ->from('borrow_items S')
            ->join('inventory_items I', [['I.product_no', 'S.product_no']], 'INNER')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'INNER')
            ->where([
                ['S.borrow_id', (int) $borrow_id],
                ['S.id', (int) $id]
            ])
            ->first();
    }

    /**
     * บันทึกการส่งมอบ/คืน/เปลี่ยนสถานะ
     * คืนค่า [save, stock, errors, alert] — save/stock เป็นค่าที่จะอัปเดต (null ถ้าไม่มี)
     * alert เป็นข้อความ error ระดับทั้งฟอร์ม (ปิด modal ตามระบบเดิม)
     *
     * @param string $action delivery|return|status
     * @param int    $amount จำนวนที่ส่งมอบ/คืน
     * @param int    $status สถานะที่เลือก
     * @param object $index  ข้อมูลรายการจาก get()
     *
     * @return array
     */
    public static function submit($action, $amount, $status, $index)
    {
        $errors = [];
        $alert = '';
        $save = null;
        $stock = null;
        if ($action === 'status') {
            // เปลี่ยนสถานะเท่านั้น
            if ($status == 3 && $index->amount != 0) {
                // คุณยังไม่ได้คืนพัสดุ กรุณาคืนพัสดุก่อน
                $alert = \Kotchasan\Language::get('You have not returned the equipment. Please return it first.');
            } else {
                $save = ['status' => $status];
            }
        } elseif ($action === 'delivery') {
            // ส่งมอบ
            if ($amount > 0) {
                if ($amount + $index->amount > $index->num_requests) {
                    // จำนวนที่ส่งมอบมากกว่าจำนวนที่ยืม
                    $errors['amount'] = \Kotchasan\Language::get('The amount delivered is greater than the amount borrowed');
                } elseif ($index->count_stock == 0) {
                    // สต็อกไม่จำกัด อัปเดตรายการ
                    $save = ['amount' => $index->amount + $amount, 'status' => $status];
                } else {
                    if ($amount > $index->stock) {
                        // สต็อกไม่เพียงพอ
                        $errors['amount'] = \Kotchasan\Language::replace('There is not enough :name (remaining :stock :unit)', [':name' => $index->topic, ':stock' => $index->stock, ':unit' => $index->unit]);
                    } else {
                        // ตัดสต็อก — เก็บเป็น "ส่วนต่าง" ให้ save() ไปลงสมุดบัญชี
                        // ห้ามคำนวณยอดใหม่แล้วเขียนทับ เพราะยอดที่อ่านมาอาจเก่าไปแล้ว
                        // ถ้ามีคนอื่นส่งมอบพร้อมกัน (เขียนทับ = ยอดของอีกคนหายไปเงียบ ๆ)
                        $save = ['amount' => $index->amount + $amount, 'status' => $status];
                        $stock = ['delta' => -$amount];
                    }
                }
            } else {
                $errors['amount'] = \Kotchasan\Language::get('Please fill in');
            }
        } elseif ($action === 'return') {
            // คืนพัสดุ
            if ($amount > 0) {
                if ($index->count_stock == 0) {
                    // สต็อกไม่จำกัด อัปเดตรายการ
                    $save = ['amount' => $index->amount - $amount, 'status' => $status];
                } else {
                    if ($status == 3 && $amount != $index->amount) {
                        // จำนวนที่คืนไม่เท่ากับจำนวนที่ส่งมอบ
                        $alert = \Kotchasan\Language::get('The amount returned is greater than the amount delivered');
                    } elseif ($amount > $index->amount) {
                        // จำนวนที่คืนมากกว่าจำนวนที่ส่งมอบ
                        $alert = \Kotchasan\Language::get('The amount returned is greater than the amount delivered');
                    } else {
                        // คืนสต็อก — ส่วนต่างเป็นบวก
                        $save = ['amount' => $index->amount - $amount, 'status' => $status];
                        $stock = ['delta' => $amount];
                    }
                }
            } else {
                $errors['amount'] = \Kotchasan\Language::get('Please fill in');
            }
        }
        return [$save, $stock, $errors, $alert];
    }

    /**
     * บันทึกผลการตรวจสอบลงฐานข้อมูล
     *
     * @param object $index ข้อมูลรายการ
     * @param array  $save  ค่าที่อัปเดต borrow_items
     * @param array  $stock ค่าที่อัปเดต inventory_items (null ถ้าไม่มี)
     */
    public static function save($index, $save, $stock)
    {
        $db = \Kotchasan\DB::create();
        if ($stock !== null && !empty($stock['delta'])) {
            // ⚠️ โมดูล inventory ของโปรเจ็คนี้ไม่มีสมุดบัญชีเดินสต๊อก (Posting)
            //
            // ความจริงของยอดคงเหลืออยู่ที่ inventory_items.stock ตรง ๆ ส่วน
            // inventory.stock เป็นผลรวมของทุกเลขครุภัณฑ์ซึ่งต้องคำนวณใหม่
            // ทุกครั้งที่แถวเปลี่ยน (ดู \Inventory\Items\Model)
            //
            // เขียนเป็น "ส่วนต่าง" ให้ฐานข้อมูลบวกลบเอง ห้ามอ่านยอดมาคำนวณ
            // แล้วเขียนทับ เพราะยอดที่อ่านมาอาจเก่าไปแล้วถ้ามีคนอื่นส่งมอบ
            // พร้อมกัน (เขียนทับ = ยอดของอีกคนหายไปเงียบ ๆ)
            $delta = (float) $stock['delta'];
            $db->update('inventory_items', [
                ['id', (int) $index->inventory_item_id]
            ], [
                'stock' => \Kotchasan\Database\Sql::create('`stock` '.($delta < 0 ? '-' : '+').' '.abs($delta)),
                'last_update' => time()
            ]);
            \Inventory\Items\Model::syncProductStock((int) $index->inventory_id);
        }
        $db->update('borrow_items', [
            ['borrow_id', $index->borrow_id],
            ['id', $index->id]
        ], $save);
    }
}
