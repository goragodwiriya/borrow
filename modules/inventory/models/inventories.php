<?php
/**
 * @filesource modules/inventory/models/inventories.php
 *
 * ทะเบียนพัสดุ — รายการพัสดุทั้งหมด (รวมที่ปิดการใช้งานแล้ว)
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Inventories;

/**
 * Model ทะเบียนพัสดุ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query สำหรับตารางทะเบียนพัสดุ
     *
     * แสดงหนึ่งบรรทัดต่อหนึ่งรายการเสมอ ไม่แตกตามเลขครุภัณฑ์ (ต้นฉบับ LEFT JOIN
     * แล้วไม่ได้ GROUP BY พัสดุที่มีหลายรหัสจึงขึ้นซ้ำหลายบรรทัด)
     * และแสดงของที่ปิดการใช้งานแล้วด้วย
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [];
        foreach (['category_id', 'model_id', 'type_id'] as $_key) {
            if (isset($params[$_key]) && $params[$_key] !== '') {
                $where[] = ['V.'.$_key, (string) $params[$_key]];
            }
        }
        if (isset($params['is_active']) && $params['is_active'] !== '') {
            $where[] = ['V.is_active', (int) $params['is_active']];
        }

        $query = static::createQuery()
            ->select('V.id', 'V.topic', 'V.description', 'V.category_id', 'V.model_id', 'V.type_id',
                'V.unit', 'V.stock', 'V.is_active')
            ->selectRaw('COUNT(I.`id`) AS `items`')
        // พัสดุหนึ่งรายการมีเลขครุภัณฑ์ได้หลายสิบตัว ต่อกันทั้งหมดคอลัมน์เดียว
        // จะยาวจนดันตารางเสียรูป จึงส่งมาแค่สองตัวแรกแล้วบอกส่วนที่เหลือเป็น +N
        // (ดูครบทุกตัวได้ที่หน้าแก้ไขพัสดุ)
            ->selectRaw('CASE WHEN COUNT(I.`product_no`) > 2'
                .' THEN CONCAT(SUBSTRING_INDEX(GROUP_CONCAT(I.`product_no` ORDER BY I.`product_no` SEPARATOR ", "), ", ", 2), " +", COUNT(I.`product_no`) - 2)'
                .' ELSE GROUP_CONCAT(I.`product_no` ORDER BY I.`product_no` SEPARATOR ", ")'
                .' END AS `product_no`')
            ->from('inventory V')
            ->join('inventory_items I', ['I.inventory_id', 'V.id'], 'LEFT')
            ->groupBy('V.id');

        if (!empty($where)) {
            $query->where($where);
        }

        // ค้นหาต้องครอบเลขครุภัณฑ์ด้วย ผู้ใช้ถือของอยู่ในมือแล้วพิมพ์เลขบนสติกเกอร์
        // เป็นวิธีค้นที่ใช้บ่อยที่สุดของทะเบียนพัสดุ
        //
        // ⚠️ ต้องเป็น where() อีกครั้งแยกต่างหาก ไม่ใช่ยัดลงในอาเรย์ชุดเดิม
        // where() หนึ่งครั้ง = วงเล็บหนึ่งชุด ครั้งถัด ๆ ไปต่อด้วย AND เสมอ
        // ถ้ารวมชุดเดียวกันเงื่อนไข OR จะกลืนตัวกรองหมวดหมู่ทิ้ง
        if (isset($params['search']) && $params['search'] !== '') {
            $keyword = '%'.$params['search'].'%';
            $query->where([
                ['V.topic', 'LIKE', $keyword],
                ['V.description', 'LIKE', $keyword],
                ['I.product_no', 'LIKE', $keyword]
            ], 'OR');
        }

        return $query;
    }
}
