<?php
/**
 * @filesource modules/index/controllers/forgot.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Forgot;

use Kotchasan\Http\Request;

/**
 * module=forgot.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ขอรหัสผ่านใหม่.
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ฟอร์ม register
        $index = \Index\Welcome\View::forgot($request);
        // ข้อความ title bar
        $this->title = $index->title;
        // เลือกเมนู
        $this->menu = 'signin';
        // คืนค่า HTML

        return $index->detail;
    }
}
