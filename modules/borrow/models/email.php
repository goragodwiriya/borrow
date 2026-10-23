<?php
/**
 * @filesource modules/borrow/models/email.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Email;

use Kotchasan\Date;
use Kotchasan\Language;

/**
 * ส่งอีเมล LINE และ Telegram แจ้งผู้ที่เกี่ยวข้องกับใบยืม
 * (ผู้ยืม + เจ้าหน้าที่ที่มีสิทธิ์ can_approve_borrow)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ส่งข้อความแจ้งการทำรายการ
     * คืนค่าข้อความผลการส่ง
     *
     * @param array $order ข้อมูลใบยืม (ต้องมี id, borrower_id, borrow_no, transaction_date, borrow_date, return_date)
     *
     * @return string
     */
    public static function send($order)
    {
        $lines = [];
        $emails = [];
        $telegrams = [];
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }
        $name = '';
        $mailto = '';
        $line_uid = '';
        $telegram_id = '';
        // ตรวจสอบรายชื่อผู้รับ
        if (!empty(self::$cfg->demo_mode)) {
            // โหมดตัวอย่าง ส่งหาผู้ทำรายการและแอดมินเท่านั้น
            $where = [
                ['id', [$order['borrower_id'], 1]]
            ];
        } else {
            // ส่งหาผู้ทำรายการและผู้ที่เกี่ยวข้อง
            $where = [
                ['id', $order['borrower_id']],
                ['status', 1],
                ['permission', 'LIKE', '%,can_approve_borrow,%']
            ];
        }
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'username', 'name', 'line_uid', 'telegram_id')
            ->from('user')
            ->where(['active', 1])
            ->where($where, 'OR')
            ->cacheOn();
        foreach ($query->execute() as $item) {
            if ($item->id == $order['borrower_id']) {
                // ผู้ยืม
                $name = $item->name;
                $mailto = $item->username;
                $line_uid = $item->line_uid;
                $telegram_id = $item->telegram_id;
            } else {
                // เจ้าหน้าที่
                $emails[] = $item->name.'<'.$item->username.'>';
                if (!empty($item->line_uid)) {
                    $lines[] = $item->line_uid;
                }
                if (!empty($item->telegram_id)) {
                    $telegrams[$item->telegram_id] = $item->telegram_id;
                }
            }
        }
        // ข้อความ
        $msg = [
            '{LNG_Borrow} & {LNG_Return} : '.$order['borrow_no'],
            '{LNG_Borrower} : '.$name,
            '{LNG_Transaction date} : '.Date::format($order['transaction_date'], 'd M Y'),
            '{LNG_Borrowed date} : '.Date::format($order['borrow_date'], 'd M Y'),
            '{LNG_Date of return} : '.Date::format($order['return_date'], 'd M Y')
        ];
        $item_status = 0;
        foreach (\Borrow\Order\Model::items($order['id']) as $item) {
            $item_status = $item['status'];
            $msg[] = $item['topic'].' ['.$item['product_no'].'] '.$item['num_requests'].' '.$item['unit'].' ('.Language::get('BORROW_STATUS', null, $item['status']).')';
        }
        // ข้อความของ user
        $msg = Language::trans(implode("\n", $msg));
        // ข้อความของแอดมิน (เพิ่มลิงก์ไปยังรายงานตามสถานะ)
        // Router ของ Now.js ใช้โหมด history ไม่ใช่ hash ลิงก์จึงต้องเป็น path ปกติ
        $admin_msg = $msg."\nURL : ".WEB_URL.'borrow-report?status='.$item_status;
        // ส่งข้อความ
        $ret = [];
        if (!empty(self::$cfg->telegram_bot_token)) {
            // Telegram (แอดมิน)
            $err = \Gcms\Telegram::sendTo($telegrams, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
            // Telegram (ผู้ยืม)
            $err = \Gcms\Telegram::sendTo($telegram_id, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty(self::$cfg->line_channel_access_token)) {
            // LINE (แอดมิน)
            $err = \Gcms\Line::sendTo($lines, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
            // LINE (ผู้ยืม)
            $err = \Gcms\Line::sendTo($line_uid, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (self::$cfg->noreply_email != '') {
            // หัวข้ออีเมล
            $subject = '['.self::$cfg->web_title.'] '.Language::trans('{LNG_Borrow} & {LNG_Return}');
            // ส่งอีเมลไปยังผู้ยืมเสมอ
            $err = \Kotchasan\Email::send($name.'<'.$mailto.'>', self::$cfg->noreply_email, $subject, nl2br($msg));
            if ($err->error()) {
                $ret[] = strip_tags($err->getErrorMessage());
            }
            // รายละเอียดในอีเมล (แอดมิน)
            $admin_msg = nl2br($admin_msg);
            foreach ($emails as $item) {
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $admin_msg);
                if ($err->error()) {
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            // ส่งข้อความสำเร็จ หรือ error การส่ง
            return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
        } else {
            // ไม่มีข้อความต้องส่ง
            return Language::get('Saved successfully');
        }
    }
}
