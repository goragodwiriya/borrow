<?php
/**
 * @filesource modules/inventory/models/product.php
 *
 * ทะเบียนพัสดุ — พัสดุหนึ่งรายการและเลขครุภัณฑ์ของมัน
 *
 * ⚠️ ตารางมีคอลัมน์ของฝั่งซื้อขายอยู่ (price · vat · cost · count_stock ·
 *    stockable · allow_negative) เพราะสคีมาต้องตรงกับ oas — ผลิตภัณฑ์นี้
 *    ไม่มีการซื้อขาย จึงไม่อ่านและไม่เขียนคอลัมน์เหล่านั้นเลย ปล่อยเป็นค่า
 *    ปริยายของตาราง
 *
 * ยอดคงเหลือของพัสดุ (คอลัมน์ stock) เป็นผลรวมของเลขครุภัณฑ์ทุกรหัส
 * คำนวณโดย Inventory\Items\Model::syncProductStock() ที่เดียว
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Product;

use Inventory\Base\Model as Base;
use Inventory\Items\Model as Items;

/**
 * Model ของพัสดุหนึ่งรายการ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ชื่อข้อมูลเพิ่มเติมของพัสดุ (ตาราง inventory_meta คอลัมน์ name)
     * คืนค่า [name => label] ประกาศไว้ในภาษา INVENTORY_METAS
     *
     * โมดูลอื่น (เช่น borrow) อ่านผ่านเมธอดนี้ ไม่ใช่ property คงที่
     * เพราะ label ต้องแปลตามภาษาที่ผู้ใช้เลือกในแต่ละ request
     *
     * @return array
     */
    public static function metas()
    {
        $metas = \Kotchasan\Language::get('INVENTORY_METAS', []);

        return is_array($metas) ? $metas : [];
    }

    /**
     * อ่านพัสดุหนึ่งรายการพร้อมเลขครุภัณฑ์ทั้งหมด
     *
     * @param int $id
     *
     * @return array|null
     */
    public static function get($id)
    {
        $row = static::createDB()->first(Base::table('inventory'), ['id' => (int) $id]);
        if (!$row) {
            return null;
        }
        $product = (array) $row;
        $product['balance'] = (float) $product['stock'];
        $product['items'] = self::items((int) $id);

        return $product;
    }

    /**
     * เลขครุภัณฑ์ของพัสดุ
     *
     * @param int $inventoryId
     *
     * @return array
     */
    public static function items($inventoryId)
    {
        return static::createQuery()
            ->select('id', 'sku', 'product_no', 'barcode', 'topic', 'unit', 'stock')
            ->from('inventory_items')
            ->where(['inventory_id', (int) $inventoryId])
            ->orderBy('id')
            ->execute(null, 'array')
            ->fetchAll();
    }

    /**
     * สร้างพัสดุใหม่
     *
     * ⚠️ ระบบเดิมของ oms ส่งคอลัมน์ `create_date` ลงตาราง inventory ที่ไม่มี
     * คอลัมน์นั้น และ Kotchasan ไม่กรองคอลัมน์ให้ ผลคือ **เพิ่มรายการใหม่พัง
     * ทุกครั้ง** ตรงนี้จึงกรองคีย์ที่รู้จักเท่านั้นก่อนเขียนเสมอ
     *
     * @param array $data
     *
     * @throws \Exception เมื่อข้อมูลไม่ครบ
     *
     * @return int id ของพัสดุที่สร้าง
     */
    public static function createProduct(array $data)
    {
        $row = self::prepare($data);
        if ($row['topic'] === '') {
            throw new \Exception('กรุณากรอกชื่อพัสดุ');
        }
        $row['created_at'] = date('Y-m-d H:i:s');
        $row['last_update'] = time();

        return (int) static::createDB()->insert(Base::table('inventory'), $row);
    }

    /**
     * แก้ไขพัสดุ
     *
     * ชื่อเมธอดไม่ใช่ update() เพราะ Kotchasan\Model มี update($table) เป็น
     * เมธอดของอินสแตนซ์อยู่แล้ว ประกาศทับเป็น static ไม่ได้ (fatal error)
     *
     * @param int   $id
     * @param array $data
     *
     * @return bool
     */
    public static function updateProduct($id, array $data)
    {
        $id = (int) $id;
        $db = static::createDB();
        if (!$db->exists(Base::table('inventory'), ['id' => $id])) {
            return false;
        }
        $row = self::prepare($data);
        // ⚠️ เขียนเฉพาะคอลัมน์ที่ฟอร์มส่งมาจริง
        //
        // prepare() คืนคอลัมน์ครบทุกตัวพร้อมค่าปริยาย ถ้าเขียนทั้งชุด ฟอร์มที่
        // ไม่มีช่องนั้น (เช่นการนำเข้าที่ส่งมาไม่ครบ) จะล้างค่าเดิมทุกครั้งที่บันทึก
        // คอลัมน์ที่ prepare() คำนวณเองจากตัวอื่นต้องเขียนตามตัวที่มันอ้างอิง
        $_derived = [
            'is_active' => ['is_active', 'inuse'],
            'inuse' => ['is_active', 'inuse']
        ];
        foreach (array_keys($row) as $_col) {
            $_sources = isset($_derived[$_col]) ? $_derived[$_col] : [$_col];
            $_given = false;
            foreach ($_sources as $_src) {
                if (array_key_exists($_src, $data)) {
                    $_given = true;
                    break;
                }
            }
            if (!$_given) {
                unset($row[$_col]);
            }
        }
        if (empty($row)) {
            return true;
        }
        $row['updated_at'] = date('Y-m-d H:i:s');
        $row['last_update'] = time();
        $db->update(Base::table('inventory'), ['id', $id], $row);

        return true;
    }

    /**
     * ลบพัสดุพร้อมเลขครุภัณฑ์และข้อมูลเสริมทั้งหมด
     *
     * @param int $id
     *
     * @return bool
     */
    public static function remove($id)
    {
        $id = (int) $id;
        $db = static::createDB();
        if (!$db->exists(Base::table('inventory'), ['id' => $id])) {
            return false;
        }
        $db->delete(Base::table('inventory_items'), ['inventory_id', $id], 0);
        $db->delete(Base::table('inventory_meta'), ['inventory_id', $id], 0);
        $db->delete(Base::table('inventory'), ['id', $id]);

        return true;
    }

    /**
     * เพิ่มหรือแก้ไขเลขครุภัณฑ์หนึ่งรหัส
     *
     * ใช้ตอนสร้างพัสดุใหม่ (รหัสแรกมาจากฟอร์มเดียวกัน แบบต้นฉบับ) และการนำเข้า
     * การแก้ทั้งชุดอยู่ที่ Inventory\Items\Model::save()
     *
     * @param int   $inventoryId
     * @param array $data ต้องมี sku หรือ product_no อย่างน้อยหนึ่งอย่าง
     * @param int   $itemId 0 = เพิ่มใหม่
     * @param bool  $sync   false = ยังไม่ต้องคำนวณยอดของพัสดุตอนนี้ ผู้เรียกจะ
     *                      เรียก Items\Model::syncProductStocks() ทีเดียวเมื่อจบ
     *                      (ใช้ตอนนำเข้าไฟล์ ซึ่งแตะพัสดุเดิมซ้ำหลายบรรทัด)
     *
     * @throws \Exception เมื่อรหัสซ้ำ
     *
     * @return int id ของแถว
     */
    public static function saveItem($inventoryId, array $data, $itemId = 0, $sync = true)
    {
        $sku = isset($data['sku']) ? trim((string) $data['sku']) : '';
        if ($sku === '' && isset($data['product_no'])) {
            $sku = trim((string) $data['product_no']);
        }
        if ($sku === '') {
            throw new \Exception('กรุณากรอกเลขครุภัณฑ์');
        }

        $db = static::createDB();
        $table = Base::table('inventory_items');
        $exists = $db->first($table, ['sku' => $sku]);
        if ($exists && (int) $exists->id !== (int) $itemId) {
            throw new \Exception('เลขครุภัณฑ์ '.$sku.' ถูกใช้ไปแล้ว');
        }

        $row = [
            'sku' => $sku,
            'product_no' => $sku,
            'inventory_id' => (int) $inventoryId,
            'barcode' => isset($data['barcode']) && $data['barcode'] !== '' ? $data['barcode'] : null,
            'topic' => isset($data['topic']) ? $data['topic'] : null,
            'unit' => isset($data['unit']) ? $data['unit'] : null,
            'stock' => isset($data['stock']) ? (float) $data['stock'] : 0,
            // [oms] คงไว้ให้สคีมาตรงกับ oas — โมดูลนี้ไม่ได้ใช้กรองอะไร
            'instock' => 1,
            'last_update' => time()
        ];
        if ((int) $itemId > 0) {
            $db->update($table, ['id', (int) $itemId], $row);
            $id = (int) $itemId;
        } else {
            $id = (int) $db->insert($table, $row);
        }

        if ($sync) {
            Items::syncProductStock($inventoryId);
        }

        return $id;
    }

    /**
     * โฟลเดอร์เก็บรูปพัสดุ (เส้นทางเดียวกับระบบเดิม datas/inventory/{id}.webp)
     *
     * @return string
     */
    public static function imageDir()
    {
        return ROOT_PATH.DATA_FOLDER.'inventory/';
    }

    /**
     * ชื่อไฟล์รูปของพัสดุหนึ่งรายการ
     *
     * @param int $id
     *
     * @return string
     */
    public static function imageName($id)
    {
        return ((int) $id).self::$cfg->stored_img_type;
    }

    /**
     * URL รูปพัสดุ ถ้ามีไฟล์อยู่จริง
     *
     * @param int $id
     *
     * @return string
     */
    public static function imageUrl($id)
    {
        $file = self::imageDir().self::imageName($id);
        if (!is_file($file)) {
            return WEB_URL.'images/no-image.webp';
        }

        // ต่อเวลาแก้ไขไฟล์ท้าย URL กันเบราว์เซอร์แสดงรูปเก่าหลังอัปโหลดทับ
        return WEB_URL.DATA_FOLDER.'inventory/'.self::imageName($id).'?'.filemtime($file);
    }

    /**
     * ลบรูปพัสดุ
     *
     * @param int $id
     *
     * @return bool
     */
    public static function removeImage($id)
    {
        $file = self::imageDir().self::imageName($id);
        if (is_file($file)) {
            return @unlink($file);
        }

        return false;
    }

    /**
     * แปลงข้อมูลที่รับมาให้เป็นแถวที่เขียนลงตารางได้ กรองคีย์ที่ไม่รู้จักทิ้ง
     *
     * ชื่อกลางกับชื่อเดิมต้องเขียนคู่กันเสมอ — ระบบเดิมและรายงานที่ผู้ใช้เขียนเอง
     * ยังอ่านคอลัมน์เดิมอยู่ ปล่อยให้ค้างค่าเก่าไม่ได้
     *
     * @param array $data
     *
     * @return array
     */
    protected static function prepare(array $data)
    {
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : (isset($data['inuse']) ? (int) $data['inuse'] : 1);

        return [
            'topic' => isset($data['topic']) ? trim((string) $data['topic']) : '',
            'description' => isset($data['description']) ? $data['description'] : null,
            'category_id' => isset($data['category_id']) && $data['category_id'] !== '' ? (string) $data['category_id'] : null,
            'model_id' => isset($data['model_id']) && $data['model_id'] !== '' ? (string) $data['model_id'] : null,
            'type_id' => isset($data['type_id']) && $data['type_id'] !== '' ? (string) $data['type_id'] : null,
            'unit' => isset($data['unit']) ? $data['unit'] : null,
            'is_active' => $isActive,
            'inuse' => $isActive
        ];
    }
}
