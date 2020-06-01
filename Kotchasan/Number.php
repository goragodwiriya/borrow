<?php
/**
 * @filesource Kotchasan/Number.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Kotchasan;

/**
 * ฟังก์ชั่นตัวเลข
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Number
{
    /**
     * ฟังก์ชั่น เติม comma รองรับจุดทศนิยม
     * ถ้าไม่มีทศนิยมคืนค่า จำนวนเต็ม
     * ไม่ปัดเศษ
     *
     * @assert (100) [==] "100"
     * @assert (100.1) [==] "100.1"
     * @assert (1000.12) [==] "1,000.12"
     * @assert (1000.1555) [==] "1,000.1555"
     *
     * @param float  $value
     * @param string $thousands_sep (optional) เครื่องหมายหลักพัน (default ,)
     *
     * @return string
     */
    public static function format($value, $thousands_sep = ',')
    {
        $values = explode('.', $value);

        return number_format((float) $values[0], 0, '', $thousands_sep).(empty($values[1]) ? '' : '.'.$values[1]);
    }

    /**
     * หังก์ชั่นหาร
     * $divisor เท่ากับ 0 คืนค่า 0
     *
     * @param $actual ตัวตั้ง
     * @param $divisor ตัวหาร
     *
     * @return mixed
     */
    public static function division($actual, $divisor)
    {
        return empty($divisor) ? 0 : $actual / $divisor;
    }
}
