<?php
/**
 * @filesource modules/borrow/models/inventory.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Kotchasan\Database\Sql;

/**
 * คลังพัสดุ (มุมมองของผู้ยืม แสดงเฉพาะพัสดุที่เปิดใช้งาน)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับ DataTable
     * 1 แถวคือ 1 เลขครุภัณฑ์ เช่นเดียวกับหน้าจัดการพัสดุ
     *
     * @param array $params [category_id, type_id, model_id, search]
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [
            ['V.is_active', 1]
        ];
        foreach (['category_id', 'type_id', 'model_id'] as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $where[] = ['V.'.$key, $params[$key]];
            }
        }

        // ⚠️ id ของแถวคือเลขครุภัณฑ์ ไม่ใช่ V.id
        //
        // หนึ่งแถวคือหนึ่งเลขครุภัณฑ์ พัสดุหนึ่งรายการมีได้หลายเลข ถ้าใช้ V.id
        // ทุกแถวของพัสดุเดียวกันจะมี id ซ้ำกัน (ตารางฝั่งหน้าเว็บหาแถวจาก id
        // ไม่เจอตัวที่กดจริง) และปุ่มรายละเอียด/ยืมซึ่งส่ง id ไปค้นด้วย
        // product_no ก็จะไม่พบข้อมูล — V.id ยังต้องใช้หารูปภาพ ส่งไปเป็น
        // inventory_id ต่างหาก
        $query = static::createQuery()
            ->select('I.product_no id', 'V.id inventory_id', 'V.topic', 'V.category_id', 'V.type_id', 'V.model_id', 'V.count_stock', 'I.product_no', 'I.stock', 'I.unit')
            ->from('inventory V')
            ->join('inventory_items I', [['I.inventory_id', 'V.id']], 'INNER')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['V.topic', 'LIKE', $search],
                ['I.product_no', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * อ่านข้อมูลพัสดุจากเลขครุภัณฑ์ พร้อมข้อมูลเพิ่มเติม (meta)
     *
     * @param string $product_no
     *
     * @return object|null
     */
    public static function get($product_no)
    {
        $query = static::createQuery()
            ->from('inventory V')
            ->join('inventory_items I', [['I.inventory_id', 'V.id']], 'INNER')
            ->where([['I.product_no', $product_no]])
            ->cacheOn();

        $select = ['V.*', 'I.product_no', 'I.unit', 'I.stock'];
        $n = 1;
        foreach (\Inventory\Product\Model::metas() as $key => $label) {
            $query->join('inventory_meta M'.$n, [['M'.$n.'.inventory_id', 'V.id'], ['M'.$n.'.name', $key]], 'LEFT');
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }

        return $query->select(...$select)->first();
    }

    /**
     * ค้นหาพัสดุที่ยืมได้ จากคำค้น (ชื่อพัสดุ หรือเลขครุภัณฑ์)
     * เงื่อนไขเหมือนระบบเดิม: เปิดใช้งาน และ (มีสต็อก หรือไม่นับสต็อก)
     *
     * @param string $search
     * @param int $limit
     *
     * @return array รูปแบบ [{value, text, ...}] สำหรับ autocomplete
     */
    public static function search($search, $limit = 20)
    {
        if ($search === '') {
            return [];
        }

        $keyword = '%'.$search.'%';
        $result = static::createQuery()
            ->select('V.topic', 'V.count_stock', 'I.product_no', 'I.unit', 'I.stock')
            ->from('inventory V')
            ->join('inventory_items I', [['I.inventory_id', 'V.id']], 'INNER')
            ->where([['V.is_active', 1]])
            ->where([
                ['V.topic', 'LIKE', $keyword],
                ['I.product_no', 'LIKE', $keyword]
            ], 'OR')
            ->where([
                ['I.stock', '>', 0],
                ['V.count_stock', 0]
            ], 'OR')
            ->orderBy('V.topic')
            ->orderBy('I.product_no')
            ->limit($limit)
            ->fetchAll(true);

        $datas = [];
        foreach ($result as $item) {
            $datas[] = [
                'value' => $item['product_no'],
                'text' => $item['product_no'].' : '.$item['topic'],
                'topic' => $item['topic'],
                'product_no' => $item['product_no'],
                'unit' => (string) $item['unit'],
                // count_stock = 0 คือสต็อกไม่จำกัด ส่ง -1 ให้ฝั่งหน้าเว็บ
                'stock' => empty($item['count_stock']) ? -1 : (float) $item['stock']
            ];
        }

        return $datas;
    }

    /**
     * อ่านพัสดุจากเลขครุภัณฑ์ที่แน่นอน (ใช้ตอนเลือกรายการ/สแกนบาร์โค้ด)
     * คืนค่า null ถ้ายืมไม่ได้ (ปิดใช้งาน หรือสต็อกหมด)
     *
     * @param string $product_no
     *
     * @return array|null
     */
    public static function find($product_no)
    {
        $result = static::createQuery()
            ->select('V.topic', 'V.count_stock', 'I.product_no', 'I.unit', 'I.stock')
            ->from('inventory V')
            ->join('inventory_items I', [['I.inventory_id', 'V.id']], 'INNER')
            ->where([
                ['I.product_no', $product_no],
                ['V.is_active', 1]
            ])
            ->where([
                ['I.stock', '>', 0],
                ['V.count_stock', 0]
            ], 'OR')
            ->first(true);

        if (!$result) {
            return null;
        }

        $unlimited = empty($result['count_stock']);

        return [
            'topic' => $result['topic'],
            'product_no' => $result['product_no'],
            'unit' => (string) $result['unit'],
            // สต็อกไม่จำกัดส่ง -1 เพื่อให้ฝั่งหน้าเว็บไม่จำกัดจำนวน
            'stock' => $unlimited ? -1 : (float) $result['stock'],
            'stock_text' => $unlimited
                ? \Kotchasan\Language::get('Unlimited')
                : trim(\Kotchasan\Number::format($result['stock']).' '.$result['unit'])
        ];
    }
}
