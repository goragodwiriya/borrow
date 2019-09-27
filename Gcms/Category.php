<?php
/**
 * @filesource Gcms/Category.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Gcms;

/**
 * คลาสสำหรับอ่านข้อมูลหมวดหมู่
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Category
{
    /**
     * @var array
     */
    private $datas = array();
    /**
     * @var array
     */
    protected $categories = array();
    /**
     * true รายการทั้งหมด
     * false เฉพาะรายการที่เผยแพร่
     *
     * @var bool
     */
    protected $all = true;

    /**
     * อ่านรายชื่อประเภทหมวดหมู่ที่สามารถใช้งานได้
     *
     * @return array
     */
    public function typies()
    {
        return empty($this->categories) ? array() : array_keys($this->categories);
    }

    /**
     * คืนค่าประเภทหมวดหมู่
     *
     * @return array
     */
    public function items()
    {
        return $this->categories;
    }

    /**
     * คืนค่าชื่อหมวดหมู่
     * ไม่พบคืนค่าว่าง
     *
     * @param string $type
     *
     * @return string
     */
    public function name($type)
    {
        return isset($this->categories[$type]) ? $this->categories[$type] : '';
    }

    /**
     * อ่านรายชื่อหมวดหมู่จากฐานข้อมูลตามภาษาปัจจุบัน
     * สำหรับการแสดงผล
     *
     * @return static
     */
    public static function init()
    {
        // create object
        $obj = new static();
        $typies = $obj->typies();
        if (!empty($typies)) {
            $where = array(
                array('type', $typies),
            );
            if (!$obj->all) {
                $where[] = array('published', 1);
            }
            // Query
            $query = \Kotchasan\Model::createQuery()
                ->select('category_id', 'topic', 'type')
                ->from('category')
                ->where($where)
                ->order('category_id')
                ->cacheOn();
            foreach ($query->execute() as $item) {
                $obj->datas[$item->type][$item->category_id] = $item->topic;
            }
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
        return empty($this->datas[$type]) ? array() : $this->datas[$type];
    }

    /**
     * อ่านหมวดหมู่จาก $category_id
     * ไม่พบ คืนค่าว่าง
     *
     * @param string $type
     * @param int $category_id
     *
     * @return string
     */
    public function get($type, $category_id)
    {
        return empty($this->datas[$type][$category_id]) ? '' : $this->datas[$type][$category_id];
    }

    /**
     * คืนค่าคีย์รายการแรกสุด
     * ไม่พบคืนค่า NULL
     *
     * @param string $type
     *
     * @return int|null
     */
    public function getFirstKey($type)
    {
        if (isset($this->datas[$type])) {
            reset($this->datas[$type]);

            return key($this->datas[$type]);
        }

        return null;
    }
}
