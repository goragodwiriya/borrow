<?php
/**
 * @filesource modules/borrow/models/home.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Home;

use Gcms\Login;
use Kotchasan\Database\Sql;

/**
 * โมเดลสำหรับอ่านข้อมูลแสดงในหน้า  Home.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านรายการจองวันนี้.
     *
     * @return object
     */
    public static function get($login)
    {
        // รอตรวจสอบ
        $q0 = static::createQuery()
            ->select(Sql::COUNT('S.id'))
            ->from('borrow W')
            ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
            ->join('inventory I', 'INNER', array('I.id', 'S.inventory_id'))
            ->where(array(
                array('W.borrower_id', $login['id']),
                array('S.status', 0),
            ));
        // ครบกำหนดคืน
        $q1 = static::createQuery()
            ->select(Sql::COUNT('S.id'))
            ->from('borrow W')
            ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
            ->join('inventory I', 'INNER', array('I.id', 'S.inventory_id'))
            ->where(array(
                array('W.borrower_id', $login['id']),
                array('S.status', 2),
                array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '<=', 0),
            ));
        // อนุมัติ/ใช้งานอยู่
        $q2 = static::createQuery()
            ->select(Sql::COUNT('S.id'))
            ->from('borrow W')
            ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
            ->join('inventory I', 'INNER', array('I.id', 'S.inventory_id'))
            ->where(array(
                array('W.borrower_id', $login['id']),
                array('S.status', 2),
            ))
            ->andWhere(array(
                array(Sql::DATEDIFF('W.return_date', date('Y-m-d')), '>', 0),
                Sql::ISNULL('W.return_date'),
            ), 'OR');
        if (Login::checkPermission($login, 'can_approve_borrow')) {
            // รายการรอตรวจสอบทั้งหมด
            $q3 = static::createQuery()
                ->select(Sql::COUNT('S.id'))
                ->from('borrow W')
                ->join('borrow_items S', 'INNER', array('S.borrow_id', 'W.id'))
                ->join('inventory I', 'INNER', array('I.id', 'S.inventory_id'))
                ->where(array('S.status', 0));

            return static::createQuery()->cacheOn()->first(array($q0, 'pending'), array($q1, 'returned'), array($q2, 'confirmed'), array($q3, 'allpending'));
        } else {
            return static::createQuery()->cacheOn()->first(array($q0, 'pending'), array($q1, 'returned'), array($q2, 'confirmed'));
        }
    }
}
