<?php
/**
 * @filesource modules/inventory/models/import.php
 *
 * นำเข้าทะเบียนพัสดุจากไฟล์ CSV
 *
 * ⚠️ ระบบเดิม (inventory-import) เขียนข้อมูลลงตาราง `user` — ไฟล์
 * models/customerimport.php กับ models/productimport.php ต่างกันแค่ namespace
 * และทั้งคู่เป็นสำเนาของตัวนำเข้าสมาชิก กดใช้งานจริงจะได้สมาชิกใหม่เต็มระบบ
 * ไม่ได้พัสดุเลย ที่นี่จึงเขียนใหม่ให้ลงตารางที่ถูกต้อง
 *
 * ⚠️ หนึ่งบรรทัดในไฟล์ = หนึ่งเลขครุภัณฑ์ ไม่ใช่หนึ่งพัสดุ
 * เพราะพัสดุหนึ่งรายการมีได้หลายเลข และจำนวนคงเหลือผูกอยู่กับเลข ไม่ใช่กับพัสดุ
 * พัสดุที่มีหลายเลขจึงกินหลายบรรทัดโดยใช้ชื่อพัสดุ (topic) ซ้ำกัน
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Import;

use Inventory\Base\Model as Base;

/**
 * Model นำเข้าข้อมูลจากไฟล์ CSV
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * id ของพัสดุที่ถูกแตะระหว่างนำเข้า รอคำนวณยอดทีเดียวตอนจบ
     *
     * ยอดของพัสดุเป็นผลรวมของทุกเลขครุภัณฑ์ ระหว่างนำเข้าจึงเปลี่ยนไปเรื่อย ๆ
     * และค่าที่คำนวณระหว่างทางทุกค่าถูกทับด้วยค่าถัดไปเสมอ มีแต่ค่าสุดท้าย
     * เท่านั้นที่ใช้จริง — เก็บ id ไว้แล้วคำนวณครั้งเดียวเมื่อจบไฟล์
     *
     * @var array
     */
    protected static $touched = [];

    /**
     * ชนิดข้อมูลที่นำเข้าได้ และคอลัมน์ของแต่ละชนิด
     *
     * key ของคอลัมน์ = หัวตารางในไฟล์ CSV (ต้องตรงกับที่ส่งออกไป)
     * required = คอลัมน์ที่ต้องมีในไฟล์ ไม่งั้นไม่ยอมรับไฟล์
     *
     * @return array
     */
    public static function types()
    {
        return [
            'product' => [
                'title' => '{LNG_Inventory}',
                'table' => 'inventory',
                'key' => 'product_no',
                'required' => ['product_no', 'topic'],
                // ⚠️ model กับ type เป็นหมวดหมู่ของระบบเดิม (รุ่น / ประเภทพัสดุ)
                // ถ้าไม่มีในไฟล์ ข้อมูลที่ส่งออกแล้วนำกลับเข้ามาจะเสียสองค่านี้ไป
                'columns' => ['product_no', 'topic', 'description', 'category', 'model', 'type',
                    'unit', 'stock', 'is_active'],
                'url' => '/inventory-setup'
            ]
        ];
    }

    /**
     * ข้อมูลของชนิดที่เลือก
     *
     * @param string $type
     *
     * @return array|null
     */
    public static function type($type)
    {
        $types = self::types();

        return isset($types[$type]) ? $types[$type] : null;
    }

    /**
     * รูปแบบไฟล์ CSV ที่เลือกได้ในหน้าตั้งค่า
     *
     * key = ค่าที่เก็บลง settings/config.php (csv_language) — เก็บเป็น "รูปแบบ"
     * ไม่ใช่ชื่อชาร์เซ็ต เพราะ UTF-8 กับ UTF-8 with BOM เป็นชาร์เซ็ตเดียวกัน
     * ต่างกันที่ไบต์นำ 3 ตัวเท่านั้น สองค่านี้จึงต้องแยกกันที่ระดับรูปแบบ
     *
     * import = ค่าที่ส่งให้ Csv::read()   ('AUTO' คือให้ไฟล์บอกเอง)
     * export = ค่าที่ส่งให้ Csv::send()
     *
     * ⚠️ 'auto' ใช้ได้เฉพาะตอนนำเข้า เพราะตอนส่งออกยังไม่มีไฟล์ให้ตรวจ
     * ฝั่งส่งออกจึงเทียบเท่า UTF-8 with BOM ซึ่งเป็นค่าที่ Excel ไทยเปิดได้
     *
     * @return array
     */
    public static function formats()
    {
        return [
            'auto' => [
                'label' => 'Auto detect',
                'import' => 'AUTO',
                'export' => ['charset' => 'UTF-8', 'bom' => true]
            ],
            'UTF-8-BOM' => [
                'label' => 'UTF-8 with BOM',
                'import' => 'UTF-8',
                'export' => ['charset' => 'UTF-8', 'bom' => true]
            ],
            'UTF-8' => [
                'label' => 'UTF-8',
                'import' => 'UTF-8',
                'export' => ['charset' => 'UTF-8', 'bom' => false]
            ],
            'TIS-620' => [
                'label' => 'TIS-620',
                'import' => 'TIS-620',
                'export' => ['charset' => 'TIS-620', 'bom' => false]
            ]
        ];
    }

    /**
     * รูปแบบไฟล์ CSV ที่ใช้อยู่ ตามที่ตั้งไว้ในหน้าตั้งค่าโมดูล
     *
     * ค่าที่ไม่รู้จักหรือยังไม่เคยตั้ง ตกมาที่ 'auto' เสมอ — เดาจากไฟล์ผิดพลาด
     * น้อยกว่าเดาจากค่าที่ไม่มีใครตั้ง
     *
     * @param string|null $key ระบุรูปแบบตรง ๆ (null = อ่านจากค่ากำหนด)
     *
     * @return array  มี key ของรูปแบบติดมาด้วย เพื่อให้หน้าตั้งค่าเลือกถูกตัว
     */
    public static function format($key = null)
    {
        $formats = self::formats();
        if ($key === null) {
            $key = empty(self::$cfg->csv_language) ? 'auto' : (string) self::$cfg->csv_language;
        }
        if (!isset($formats[$key])) {
            $key = 'auto';
        }

        return array_merge(['key' => $key], $formats[$key]);
    }

    /**
     * แถวข้อมูลสำหรับส่งออกเป็น CSV (ใช้เป็นไฟล์ตัวอย่างของการนำเข้าด้วย)
     *
     * @param string $type
     *
     * @return array
     */
    public static function rows($type)
    {
        if (self::type($type) === null) {
            return [];
        }

        $categories = \Inventory\Category\Controller::init();
        $rows = static::createQuery()
            ->select('I.product_no', 'V.topic', 'V.description', 'V.category_id',
                'V.model_id', 'V.type_id', 'I.unit', 'I.stock', 'V.is_active')
            ->from('inventory V')
            ->join('inventory_items I', ['I.inventory_id', 'V.id'], 'LEFT')
            ->orderBy('V.id', 'I.product_no')
            ->execute(null, 'array')
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                (string) $row['product_no'],
                $row['topic'],
                $row['description'],
                $categories->get('category_id', $row['category_id'], ''),
                $categories->get('model_id', $row['model_id'], ''),
                $categories->get('type_id', $row['type_id'], ''),
                (string) $row['unit'],
                (float) $row['stock'],
                (int) $row['is_active']
            ];
        }

        return $result;
    }

    /**
     * นำเข้าหนึ่งแถว
     *
     * คืนค่า 'new' | 'update' | 'skip' เพื่อให้ตัวเรียกนับผลรวมได้
     *
     * @param string $type
     * @param array  $row  ข้อมูลหนึ่งแถวจากไฟล์ (คีย์คือหัวตาราง)
     *
     * @return string
     */
    public static function importRow($type, array $row)
    {
        $spec = self::type($type);
        if ($spec === null) {
            return 'skip';
        }

        foreach ($spec['required'] as $need) {
            if (!isset($row[$need]) || trim((string) $row[$need]) === '') {
                return 'skip';
            }
        }

        return self::importProduct($row);
    }

    /**
     * นำเข้าพัสดุหนึ่งเลขครุภัณฑ์
     *
     * @param array $row
     *
     * @return string
     */
    protected static function importProduct(array $row)
    {
        $db = static::createDB();
        $table = Base::table('inventory');
        $tableItems = Base::table('inventory_items');

        $productNo = trim((string) $row['product_no']);
        $topic = trim((string) $row['topic']);
        $unit = (string) (isset($row['unit']) ? $row['unit'] : '');
        $stock = self::toNumber(isset($row['stock']) ? $row['stock'] : 0);

        // หมวดหมู่/รุ่น/ประเภทในไฟล์เป็น "ชื่อ" ถ้ายังไม่มีจะถูกสร้างให้
        // เหมือนตอนพิมพ์ชื่อใหม่ลงในฟอร์ม
        $categoryIds = [];
        foreach (['category' => 'category_id', 'model' => 'model_id', 'type' => 'type_id'] as $_col => $_type) {
            $_name = trim((string) (isset($row[$_col]) ? $row[$_col] : ''));
            $categoryIds[$_type] = $_name === '' || is_numeric($_name)
                ? ''
                : \Inventory\Category\Controller::save($_type, $_name);
        }

        // หน่วยนับเก็บเป็นชื่อลงคอลัมน์ unit อยู่แล้ว จดเข้ารายการแนะนำด้วย
        // เพื่อให้ฟอร์มหลังนำเข้ามีหน่วยนับจากไฟล์ให้เลือก ไม่ต้องพิมพ์ใหม่ทุกครั้ง
        if ($unit !== '') {
            \Inventory\Category\Controller::save('unit', $unit);
        }

        $data = [
            'topic' => $topic,
            'description' => (string) (isset($row['description']) ? $row['description'] : ''),
            'category_id' => $categoryIds['category_id'],
            'model_id' => $categoryIds['model_id'],
            'type_id' => $categoryIds['type_id'],
            'unit' => $unit,
            'is_active' => isset($row['is_active']) && $row['is_active'] !== '' ? (int) $row['is_active'] : 1
        ];

        // เลขครุภัณฑ์มีอยู่แล้ว = แถวเดิม อัปเดตทั้งพัสดุและจำนวนของเลขนั้น
        $item = $db->first($tableItems, ['product_no' => $productNo]);
        if (!$item) {
            $item = $db->first($tableItems, ['sku' => $productNo]);
        }
        if ($item) {
            \Inventory\Product\Model::updateProduct((int) $item->inventory_id, $data);
            \Inventory\Product\Model::saveItem((int) $item->inventory_id, [
                'product_no' => $productNo,
                'unit' => $unit,
                'stock' => $stock
            ], (int) $item->id, false);
            self::$touched[(int) $item->inventory_id] = (int) $item->inventory_id;

            return 'update';
        }

        // เลขใหม่ แต่ชื่อพัสดุอาจมีอยู่แล้ว = เพิ่มเลขให้พัสดุตัวเดิม
        // (ไฟล์หนึ่งไฟล์จึงบรรยายพัสดุที่มีหลายเลขได้ด้วยการซ้ำชื่อ)
        $found = $db->first($table, ['topic' => $topic]);
        if ($found) {
            \Inventory\Product\Model::saveItem((int) $found->id, [
                'product_no' => $productNo,
                'unit' => $unit,
                'stock' => $stock
            ], 0, false);
            self::$touched[(int) $found->id] = (int) $found->id;

            return 'update';
        }

        // ⚠️ ต้องผ่าน Product\Model — มันกรองคีย์ที่ไม่รู้จักและเขียนชื่อกลางกับ
        // ชื่อเดิมคู่กันให้เอง เขียนตารางตรง ๆ จากที่นี่คือการมีตรรกะสองชุด
        $id = \Inventory\Product\Model::createProduct($data);
        \Inventory\Product\Model::saveItem($id, [
            'product_no' => $productNo,
            'unit' => $unit,
            'stock' => $stock
        ], 0, false);
        self::$touched[(int) $id] = (int) $id;

        return 'new';
    }

    /**
     * คำนวณยอดของพัสดุทุกรายการที่ไฟล์แตะ ด้วยคำสั่งเดียว
     *
     * ต้องเรียกเมื่ออ่านไฟล์จบ **รวมถึงตอนที่อ่านล้มกลางคัน** ไม่งั้นเลขครุภัณฑ์
     * ที่เขียนไปแล้วจะไม่ถูกรวมเข้ายอดของพัสดุ หน้ารายการจะโชว์ยอดเก่าค้างไว้
     *
     * @return int จำนวนพัสดุที่ยอดเปลี่ยนจริง
     */
    public static function syncStock()
    {
        if (empty(self::$touched)) {
            return 0;
        }
        $ids = self::$touched;
        // ล้างก่อนคำนวณ เผื่อถูกเรียกซ้ำจะได้ไม่ทำงานเดิมอีกรอบ
        self::$touched = [];

        return \Inventory\Items\Model::syncProductStocks($ids);
    }

    /**
     * แปลงข้อความในไฟล์เป็นตัวเลข
     *
     * ไฟล์ที่ผู้ใช้ทำจาก Excel มักมีลูกน้ำคั่นหลักพัน ถ้า cast ตรง ๆ "1,500" จะได้ 1
     *
     * @param mixed $value
     *
     * @return float
     */
    protected static function toNumber($value)
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }
}
