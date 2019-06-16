<?php
/**
 * @filesource modules/index/controllers/login.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Login;

use Kotchasan\Http\Request;

/**
 * module=login.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * เข้าระบบ.
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ฟอร์ม login
        $index = \Index\Welcome\View::login($request);
        // ข้อความ title bar
        $this->title = $index->title;
        // เลือกเมนู
        $this->menu = 'signin';
        // คืนค่า HTML

        return $index->detail;
    }
}
