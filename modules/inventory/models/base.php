<?php
/**
 * @filesource modules/inventory/models/base.php
 *
 * ฐานร่วมของโมดูล inventory — ชื่อตารางที่มี prefix
 *
 * ⚠️ ของเดิมมีทะเบียนชนิดการเคลื่อนไหวสต๊อก (movementTypes) และสถานะภาษี
 * (taxStatuses) ติดมาจาก oas ทั้งคู่ถูกตัดพร้อมสมุดบัญชีเดินสต๊อกและฝั่งซื้อขาย
 * ถ้าวันหนึ่งโปรเจ็คนี้ต้องเดินสต๊อกจริง ให้ยกมาจาก oas ทั้งชุด อย่าเขียนใหม่
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Base;

/**
 * ฐานร่วมของโมดูล inventory
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ชื่อตารางเต็มพร้อม prefix
     *
     * ⚠️ `stock` เป็นชื่อสามัญที่ชนกับตารางของโมดูลอื่นได้ ทุกที่ในโมดูลจึงต้อง
     * เรียกผ่านเมธอดนี้ ห้ามเขียนชื่อตารางเต็มไว้ในโค้ด
     *
     * @param string $table
     *
     * @return string
     */
    public static function table($table)
    {
        return static::create()->getTableName($table);
    }
}
