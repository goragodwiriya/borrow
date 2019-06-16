<?php
/**
 * @filesource modules/index/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Autocomplete;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * ค้นหาสมาชิก สำหรับ autocomplete.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค้นหาสมาชิก สำหรับ autocomplete
     * คืนค่าเป็น JSON.
     *
     * @param Request $request
     */
    public function findUser(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && Login::isMember()) {
            $search = $request->post('name')->topic();
            $where = array();
            $select = array('id', 'name', 'email');
            $order = array();
            foreach (explode(',', $request->post('from', 'name,email')->filter('a-z,')) as $item) {
                if ($item == 'name') {
                    if ($search != '') {
                        $where[] = array('name', 'LIKE', "%$search%");
                    }
                    $order[] = 'name';
                }
                if ($item == 'email') {
                    if ($search != '') {
                        $where[] = array('email', 'LIKE', "%$search%");
                    }
                    $order[] = 'email';
                }
                if ($item == 'phone') {
                    if ($search != '') {
                        $where[] = array('phone', 'LIKE', "$search%");
                    }
                    $select[] = 'phone';
                    $order[] = 'phone';
                }
            }
            $query = $this->db()->createQuery()
                ->select($select)
                ->from('user')
                ->order($order)
                ->limit($request->post('count')->toInt())
                ->toArray();
            if (!empty($where)) {
                $query->andWhere($where, 'OR');
            }
            $result = $query->execute();
            // คืนค่า JSON
            if (!empty($result)) {
                echo json_encode($query->execute());
            }
        }
    }
}
