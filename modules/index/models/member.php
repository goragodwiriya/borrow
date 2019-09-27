<?php
/**
 * @filesource modules/index/models/member.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Member;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=member.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสำหรับใส่ลงในตาราง
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        return static::createQuery()
            ->select('id', 'username', 'name', 'active', 'social', 'phone', 'status', 'create_date', 'lastvisited', 'visited')
            ->from('user');
    }

    /**
     * ฟังก์ชั่นอ่านจำนวนสมาชิกทั้งหมด
     *
     * @return int
     */
    public static function getCount()
    {
        $query = static::createQuery()
            ->selectCount()
            ->from('user')
            ->execute();

        return $query[0]->count;
    }

    /**
     * ตารางสมาชิก (member.php).
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = array();
        // session, referer, admin, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer()) {
            if (Login::notDemoMode(Login::isAdmin())) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->toString(), $match)) {
                    if ($action === 'delete') {
                        // ลบสมาชิก
                        $this->db()->delete($this->getTableName('user'), array(
                            array('id', $match[1]),
                            array('id', '!=', 1),
                        ), 0);
                        // reload
                        $ret['location'] = 'reload';
                    } elseif ($action === 'sendpassword') {
                        // ขอรหัสผ่านใหม่
                        $query = $this->db()->createQuery()
                            ->select('id', 'username')
                            ->from('user')
                            ->where(array(
                                array('id', $match[1]),
                                array('id', '!=', 1),
                                array('social', 0),
                                array('username', '!=', ''),
                                array('active', 1),
                            ))
                            ->toArray();
                        $msgs = array();
                        foreach ($query->execute() as $item) {
                            // ส่งอีเมลขอรหัสผ่านใหม่
                            $err = \Index\Forgot\Model::execute($item['id'], $item['username']);
                            if ($err != '') {
                                $msgs[] = $err;
                            }
                        }
                        if (isset($err)) {
                            if (empty($msgs)) {
                                // ส่งอีเมล สำเร็จ
                                $ret['alert'] = Language::get('Your message was sent successfully');
                            } else {
                                // มีข้อผิดพลาด
                                $ret['alert'] = implode("\n", $msgs);
                            }
                        }
                    } elseif (preg_match('/active_([01])/', $action, $match2)) {
                        // สถานะการเข้าระบบ
                        $this->db()->update($this->getTableName('user'), array(
                            array('id', $match[1]),
                            array('id', '!=', '1'),
                        ), array(
                            'active' => (int) $match2[1],
                        ));
                        // reload
                        $ret['location'] = 'reload';
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }
}
