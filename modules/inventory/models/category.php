<?php
/**
 * @filesource modules/inventory/models/category.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Inventory\Category;

/**
 * หมวดหมู่.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * @var array
     */
    private $datas = array();

    /**
     * อ่านรายชื่อหมวดหมู่จากฐานข้อมูลตามภาษาปัจจุบัน
     * สำหรับการแสดงผล.
     *
     * @return \static
     */
    public static function init()
    {
        $obj = new static();
        // Query
        $query = \Kotchasan\Model::createQuery()
            ->select('type', 'category_id', 'topic')
            ->from('category')
            ->where(array('published', 1))
            ->order('type', 'category_id')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            $obj->datas[$item->type][$item->category_id] = $item->topic;
        }

        return $obj;
    }

    /**
     * ลิสต์รายการหมวดหมู่
     * สำหรับใส่ลงใน select
     *
     * @param string $type
     *
     * @return array
     */
    public function toSelect($type)
    {
        $result = array();
        if (isset($this->datas[$type])) {
            foreach ($this->datas[$type] as $category_id => $topic) {
                $result[$category_id] = $topic;
            }
        }

        return $result;
    }

    /**
     * อ่านหมวดหมู่จาก $category_id
     * ไม่พบ คืนค่าว่าง.
     *
     * @param string $type
     * @param int $category_id
     *
     * @return string
     */
    public function get($type, $category_id)
    {
        return isset($this->datas[$type][$category_id]) ? $this->datas[$type][$category_id] : '';
    }
}
