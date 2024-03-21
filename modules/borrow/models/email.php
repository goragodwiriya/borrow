<?php
/**
 * @filesource modules/borrow/models/email.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Email;

use Kotchasan\Date;
use Kotchasan\Language;

/**
 * ส่งอีเมลและ LINE ไปยังผู้ที่เกี่ยวข้อง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ส่งอีเมลและ LINE แจ้งการทำรายการ
     *
     * @param array $order
     *
     * @return string
     */
    public static function send($order)
    {
        $lines = [];
        $emails = [];
        $name = '';
        $mailto = '';
        $line_uid = '';
        // ตรวจสอบรายชื่อผู้รับ
        if (self::$cfg->demo_mode) {
            // โหมดตัวอย่าง ส่งหาผู้ทำรายการและแอดมินเท่านั้น
            $where = array(
                array('id', array($order['borrower_id'], 1))
            );
        } else {
            // ส่งหาผู้ทำรายการและผู้ที่เกี่ยวข้อง
            $where = array(
                array('id', $order['borrower_id']),
                array('status', 1),
                array('permission', 'LIKE', '%,can_approve_borrow,%')
            );
        }
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'username', 'name', 'line_uid')
            ->from('user')
            ->where(array('active', 1))
            ->andWhere($where, 'OR')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            if ($item->id == $order['borrower_id']) {
                // ผู้จอง
                $name = $item->name;
                $mailto = $item->username;
                $line_uid = $item->line_uid;
            } else {
                // เจ้าหน้าที่
                $emails[] = $item->name.'<'.$item->username.'>';
                if ($item->line_uid != '') {
                    $lines[] = $item->line_uid;
                }
            }
        }
        // ข้อความ
        $msg = array(
            '{LNG_Borrow} & {LNG_Return} : '.$order['borrow_no'],
            '{LNG_Borrower} : '.$name,
            '{LNG_Transaction date} : '.Date::format($order['transaction_date'], 'd M Y'),
            '{LNG_Borrowed date} : '.Date::format($order['borrow_date'], 'd M Y'),
            '{LNG_Date of return} : '.Date::format($order['return_date'], 'd M Y')
        );
        foreach (\Borrow\Order\Model::items($order['id']) as $item) {
            $msg[] = $item['topic'].' ['.$item['product_no'].'] '.$item['num_requests'].' '.$item['unit'].' ('.Language::get('BORROW_STATUS', null, $item['status']).')';
        }
        // ข้อความของ user
        $msg = Language::trans(implode("\n", $msg));
        // ข้อความของแอดมิน
        $admin_msg = $msg."\nURL : ".WEB_URL.'index.php?module=borrow-report&status='.$item['status'];
        // ส่งข้อความ
        $ret = [];
        if (!empty(self::$cfg->line_api_key)) {
            // ส่ง LINE
            $err = \Gcms\Line::send($admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        // LINE ส่วนตัว
        if (!empty($lines)) {
            $err = \Gcms\Line::sendTo($lines, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty($line_uid)) {
            $err = \Gcms\Line::sendTo($line_uid, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (self::$cfg->noreply_email != '') {
            // หัวข้ออีเมล
            $subject = '['.self::$cfg->web_title.'] '.Language::trans('{LNG_Borrow} & {LNG_Return}');
            // ส่งอีเมลไปยังผู้ทำรายการเสมอ
            $err = \Kotchasan\Email::send($name.'<'.$mailto.'>', self::$cfg->noreply_email, $subject, nl2br($msg));
            if ($err->error()) {
                // คืนค่า error
                $ret[] = strip_tags($err->getErrorMessage());
            }
            // รายละเอียดในอีเมล (แอดมิน)
            $admin_msg = nl2br($admin_msg);
            foreach ($emails as $item) {
                // ส่งอีเมล
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $admin_msg);
                if ($err->error()) {
                    // คืนค่า error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            // ส่งอีเมลสำเร็จ หรือ error การส่งเมล
            return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
        } else {
            // ไม่มีอีเมลต้องส่ง
            return Language::get('Saved successfully');
        }
    }
}
