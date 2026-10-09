<?php
/**
 * @filesource modules/inventory/controllers/categories.php
 *
 * api/inventory/categories/get|save — หมวดหมู่ของโมดูล inventory
 *
 * ใช้ตัวจัดการหมวดหมู่ของแกนทั้งดุ้น เปลี่ยนแค่ "ชนิดหมวดหมู่ที่ดูแล"
 * ตัวแกนอ่าน/เขียนตาราง category ตัวเดียวกันอยู่แล้ว ต่างกันแค่คอลัมน์ `type`
 * จึงไม่มีเหตุผลให้เขียน get()/save() ขึ้นมาใหม่ให้ต้องตามแก้สองที่
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Categories;

use Kotchasan\Language;

/**
 * หมวดหมู่ของโมดูล inventory
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Index\Categories\Controller
{
    /**
     * คืนค่าหมวดหมู่ทั้งหมด
     *
     * @return array
     */
    protected function categories()
    {
        return Language::get('INVENTORY_CATEGORIES');
    }
}
