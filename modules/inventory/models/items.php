<?php
/**
 * @filesource modules/inventory/models/items.php
 *
 * เลขครุภัณฑ์/บาร์โค้ดของพัสดุ — หนึ่งพัสดุมีได้หลายเลข
 * ระบบเดิมคือ module=inventory-write&tab=items
 *
 * ⚠️ ต่างจากรุ่นของ oas/oms ตรงที่ผลิตภัณฑ์นี้ไม่มีการซื้อขาย จึงไม่มี
 *    ราคาต่อรหัส (price) และไม่มีอัตราตัดสต๊อก (cut_stock) — สองคอลัมน์นั้น
 *    ยังอยู่ในตารางเพราะสคีมาต้องตรงกับ oas แต่โมดูลนี้ไม่อ่านและไม่เขียน
 *
 * ⚠️ จำนวนคงเหลือเก็บที่ inventory_items.stock ตรง ๆ แบบต้นฉบับ
 *    ไม่ผ่านสมุดบัญชีเดินสต๊อก เพราะทะเบียนพัสดุต้องการรู้แค่ "มีอยู่กี่ชิ้น"
 *    ไม่ได้ต้องการประวัติการเคลื่อนไหว ยอดของพัสดุ (inventory.stock) จึงเป็น
 *    ผลรวมของทุกเลขครุภัณฑ์ คำนวณใหม่ทุกครั้งที่บันทึก
 *
 * ⚠️ ไม่ลบแล้วแทรกใหม่ทั้งชุด — โมดูล repair อ้างพัสดุด้วย product_no
 *    ถ้าลบทิ้งแล้วใส่กลับ ใบแจ้งซ่อมเดิมจะชี้ไปหารหัสที่หายไปชั่วขณะ
 *    จึงอัปเดตทับตามรหัส แล้วค่อยลบเฉพาะรหัสที่หายไปจริง
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Items;

use Inventory\Base\Model as Base;
use Kotchasan\Language;

/**
 * Model เลขครุภัณฑ์/บาร์โค้ดของพัสดุ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * เลขครุภัณฑ์ของพัสดุหนึ่งรายการ สำหรับใส่ในตารางแก้ไข
     *
     * ระบบเดิมคืนแถวว่างหนึ่งแถวเมื่อยังไม่มีข้อมูล เพื่อให้ผู้ใช้พิมพ์ได้เลย
     * ที่นี่ทำเหมือนกัน
     *
     * @param array $product ข้อมูลพัสดุ (ต้องมี id)
     *
     * @return array
     */
    public static function toDataTable(array $product)
    {
        $rows = static::createQuery()
            ->select('I.id', 'I.sku', 'I.product_no', 'I.topic', 'I.unit', 'I.stock')
            ->from('inventory_items I')
            ->where(['I.inventory_id', (int) $product['id']])
            ->orderBy('I.sku')
            ->execute(null, 'array')
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = self::formatRow($row);
        }

        if (empty($result)) {
            $result[] = self::formatRow([
                'product_no' => '',
                'sku' => '',
                'topic' => '',
                'unit' => '',
                'stock' => 1
            ]);
        }

        return $result;
    }

    /**
     * จัดรูปแบบหนึ่งแถวให้ตรงกับคอลัมน์ของตารางแก้ไข
     *
     * ⚠️ ตัวเลขจาก MariaDB เป็นสตริง ("0.00") ต้องแปลงเป็นตัวเลขจริงก่อนส่งออก
     * ไม่งั้นช่องตัวเลขในตารางจะเพี้ยน
     *
     * @param array $row
     *
     * @return array
     */
    protected static function formatRow(array $row)
    {
        $code = self::codeOf($row);

        return [
            'product_no' => $code,
            'barcode' => self::barcodeImage($code),
            'topic' => (string) $row['topic'],
            'stock' => $row['stock'] === '' || $row['stock'] === null ? 0 : (float) $row['stock'],
            'unit' => (string) $row['unit']
        ];
    }

    /**
     * รหัสของหนึ่งแถว
     *
     * สคีมากลางใช้ `sku` เป็นชื่อหลัก ส่วน `product_no` คงไว้เพราะโมดูล repair
     * และระบบเดิมอ่านคอลัมน์นั้น แถวที่ย้ายมาจากระบบเดิมอาจมีแค่ product_no
     *
     * @param array $row
     *
     * @return string
     */
    protected static function codeOf(array $row)
    {
        if (isset($row['sku']) && trim((string) $row['sku']) !== '') {
            return trim((string) $row['sku']);
        }

        return isset($row['product_no']) ? trim((string) $row['product_no']) : '';
    }

    /**
     * รูปบาร์โค้ดเป็น data URI
     *
     * ระบบเดิมวาดบาร์โค้ดฝั่ง PHP แล้วฝังเป็น base64 ในตาราง ที่นี่ทำแบบเดียวกัน
     * จะได้ไม่ต้องมี endpoint รูปภาพเพิ่มและไม่ต้องใช้ไลบรารีฝั่งเบราว์เซอร์
     *
     * @param string $productNo
     *
     * @return string ค่าว่างถ้าไม่มีรหัส
     */
    public static function barcodeImage($productNo)
    {
        if ($productNo === '') {
            return '';
        }

        // ไม่ใส่ label ในรูป (fontSize 0) เพราะฟอนต์ที่ Barcode ใช้ไม่มีในโปรเจ็คนี้
        // รหัสแสดงอยู่ในช่องข้อความข้าง ๆ อยู่แล้ว ผู้ใช้จึงเห็นครบเหมือนเดิม
        return 'data:image/png;base64,'.base64_encode(
            \Kotchasan\Barcode::create($productNo, 30)->toPng()
        );
    }

    /**
     * บันทึกเลขครุภัณฑ์ทั้งชุดของพัสดุหนึ่งรายการ
     *
     * คงพฤติกรรมที่ผู้ใช้เห็นไว้ครบ
     *  - รหัสซ้ำภายในชุดเดียวกัน และซ้ำกับพัสดุตัวอื่น = ไม่ผ่าน
     *  - รหัสที่หายไปจากฟอร์ม = ลบทิ้ง (ต้นฉบับก็ลบ)
     *  - ยอดของพัสดุคำนวณใหม่จากผลรวมของทุกรหัสเสมอ
     *
     * @param array $product  ข้อมูลพัสดุ (ต้องมี id)
     * @param array $rows     แถวจากฟอร์ม [['product_no'=>..,'topic'=>..,'stock'=>..,'unit'=>..], ...]
     * @param int   $memberId
     *
     * @return array errors รายคีย์ ว่าง = สำเร็จ
     */
    public static function save(array $product, array $rows, $memberId)
    {
        $db = static::createDB();
        $tableItems = Base::table('inventory_items');
        $id = (int) $product['id'];

        $duplicated = Language::replace('This :name already exist', [
            ':name' => Language::get('Serial/Registration No.')
        ]);

        // แถวที่มีอยู่ตอนนี้ของพัสดุตัวนี้ — เก็บ id ไว้เพื่ออัปเดตทับ ไม่ใช่ลบทิ้ง
        $existing = [];
        foreach ($db->select($tableItems, ['inventory_id', $id]) as $row) {
            $row = (array) $row;
            $existing[self::codeOf($row)] = $row;
        }

        $errors = [];
        $items = [];
        foreach ($rows as $index => $row) {
            $code = isset($row['product_no']) ? trim((string) $row['product_no']) : '';
            if ($code === '') {
                continue;
            }
            if (isset($items[$code])) {
                $errors['product_no_'.$index] = $duplicated;
                continue;
            }

            // รหัสนี้เป็นของพัสดุตัวอื่นอยู่ = ใช้ซ้ำไม่ได้
            if (!isset($existing[$code])) {
                $found = $db->first($tableItems, ['sku', $code]);
                if (!$found) {
                    $found = $db->first($tableItems, ['product_no', $code]);
                }
                if ($found && (int) $found->inventory_id !== $id) {
                    $errors['product_no_'.$index] = $duplicated;
                    continue;
                }
            }

            $items[$code] = [
                // เขียนชื่อกลางกับชื่อเดิมคู่กันเสมอ repair และระบบเดิมอ่าน product_no
                'sku' => $code,
                'product_no' => $code,
                'inventory_id' => $id,
                'topic' => isset($row['topic']) ? (string) $row['topic'] : '',
                'unit' => isset($row['unit']) ? (string) $row['unit'] : '',
                'stock' => isset($row['stock']) ? (float) $row['stock'] : 0,
                // [oms] คงไว้ให้สคีมาตรงกับ oas — โมดูลนี้ไม่ได้ใช้กรองอะไร
                'instock' => 1,
                'last_update' => time()
            ];
        }

        if (!empty($errors)) {
            return $errors;
        }

        // ---- อัปเดตทับ/เพิ่มใหม่ โดยคง id เดิมไว้ ----
        foreach ($items as $code => $item) {
            if (isset($existing[$code])) {
                $db->update($tableItems, ['id', (int) $existing[$code]['id']], $item);
            } else {
                $db->insert($tableItems, $item);
            }
        }

        // ---- รหัสที่หายไปจากฟอร์ม ----
        foreach ($existing as $code => $row) {
            if (!isset($items[$code])) {
                $db->delete($tableItems, ['id', (int) $row['id']]);
            }
        }

        self::syncProductStock($id);

        return [];
    }

    /**
     * ปรับยอดของพัสดุให้เท่ากับผลรวมของทุกเลขครุภัณฑ์
     *
     * inventory.stock เป็นค่าที่หน้ารายการอ่านโดยตรง (ไม่ต้อง join ทุกครั้ง)
     * ความจริงอยู่ที่ inventory_items.stock จึงต้องคำนวณใหม่ทุกครั้งที่แถวเปลี่ยน
     *
     * @param int $inventoryId
     *
     * @return float ยอดรวมที่เขียนลงไป
     */
    public static function syncProductStock($inventoryId)
    {
        $inventoryId = (int) $inventoryId;
        $row = static::createQuery()
            ->selectRaw('SUM(`stock`) AS `total`')
            ->from('inventory_items')
            ->where(['inventory_id', $inventoryId])
            ->first();
        $total = $row && $row->total !== null ? (float) $row->total : 0;

        static::createDB()->update(Base::table('inventory'), ['id', $inventoryId], [
            'stock' => $total,
            'last_update' => time()
        ]);

        return $total;
    }

    /**
     * ปรับยอดของพัสดุหลายรายการพร้อมกันด้วยคำสั่งเดียว
     *
     * ใช้ตอนนำเข้าไฟล์ ซึ่งแตะพัสดุเดิมซ้ำหลายรอบ (พัสดุหนึ่งรายการกินหลาย
     * บรรทัดตามจำนวนเลขครุภัณฑ์) ถ้าคำนวณรายบรรทัดแบบ syncProductStock()
     * ยอดเดิมจะถูกคำนวณใหม่ทุกบรรทัดทั้งที่ผลลัพธ์สุดท้ายเหมือนกัน
     * ไฟล์ N บรรทัดจึงเสียคิวรีไป 2N ครั้งโดยเปล่าประโยชน์
     *
     * ให้ฐานข้อมูลรวมยอดเองด้วย subquery จึงไม่ต้องอ่านค่ากลับมาที่ PHP เลย
     *
     * @param array $inventoryIds id ของพัสดุที่ต้องคำนวณใหม่ (ซ้ำได้)
     *
     * @return int จำนวนแถวที่ยอด "เปลี่ยนจริง" — แถวที่ยอดตรงอยู่แล้วฐานข้อมูล
     *             ไม่นับให้ ค่า 0 จึงไม่ได้แปลว่าไม่ได้ทำงาน
     */
    public static function syncProductStocks(array $inventoryIds)
    {
        $ids = [];
        foreach ($inventoryIds as $_id) {
            $_id = (int) $_id;
            if ($_id > 0) {
                $ids[$_id] = $_id;
            }
        }
        if (empty($ids)) {
            return 0;
        }

        $db = static::createDB();
        $table = Base::table('inventory');
        $tableItems = Base::table('inventory_items');
        $time = time();
        $total = 0;

        // แบ่งเป็นชุด — ไฟล์ใหญ่ ๆ ทำให้ IN (...) ยาวเกินขนาดคำสั่งที่เซิร์ฟเวอร์รับไหว
        foreach (array_chunk(array_values($ids), 500) as $_chunk) {
            $total += $db->update($table, [['id', $_chunk]], [
                'stock' => \Kotchasan\Database\Sql::create(
                    '(SELECT COALESCE(SUM(`stock`), 0) FROM `'.$tableItems.'` WHERE `inventory_id` = `'.$table.'`.`id`)'
                ),
                'last_update' => $time
            ]);
        }

        return $total;
    }
}
