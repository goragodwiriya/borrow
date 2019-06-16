<?php
/**
 * @filesource modules/borrow/models/email.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Email;

use Kotchasan\Language;

/**
 * ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ส่งอีเมลแจ้งการทำรายการ
     *
     * @param string $mailto
     * @param string $topic
     * @param int    $status
     */
    public static function send($mailto, $topic, $status)
    {
        if (self::$cfg->noreply_email != '') {
            // อีเมลของมาชิกที่สามารถอนุมัติได้ทั้งหมด
            $query = \Kotchasan\Model::createQuery()
                ->select('username')
                ->from('user')
                ->where(array('social', 0))
                ->andWhere(array(
                    array('status', 1),
                    array('permission', 'LIKE', '%,can_approve_borrow,%'),
                ), 'OR')
                ->cacheOn();
            $emails = array($mailto => $mailto);
            foreach ($query->execute() as $item) {
                $emails[$item->username] = $item->username;
            }
            // ส่งอีเมล
            $title = Language::trans('{LNG_Borrow} & {LNG_Return}');
            $subject = '['.self::$cfg->web_title.'] '.$title;
            $msg = $title.' '.$topic."\n".Language::get('Status').' : '.Language::find('BORROW_STATUS', null, $status);
            $msg .= "\n".WEB_URL;
            $err = \Kotchasan\Email::send(implode(',', $emails), self::$cfg->noreply_email, $subject, nl2br($msg));
            if ($err->error()) {
                // คืนค่า error
                return strip_tags($err->getErrorMessage());
            } else {
                // คืนค่า
                return Language::get('Your message was sent successfully');
            }
        } else {
            // ไม่สามารถส่งอีเมล์ได้
            return Language::get('Saved successfully');
        }
    }
}
