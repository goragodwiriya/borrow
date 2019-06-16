<?php
/**
 * @filesource modules/index/controllers/error.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Error;

use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Template;

/**
 * Error Controller ถ้าไม่สามารถทำรายการได้.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * init Class.
     */
    public function __construct()
    {
        // ค่าเริ่มต้นของ Controller
        $this->title = static::getMessage();
        $this->menu = 'home';
        $this->status = 404;
    }

    /**
     * แสดงข้อผิดพลาด (เช่น 404 page not found)
     * สำหรับการเรียกโดย GLoader.
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $template = Template::create('', '', '404');
        $template->add(array(
            '/{TOPIC}/' => $this->title,
            '/{DETAIL}/' => $this->title,
        ));
        // คืนค่า HTML

        return $template->render();
    }

    /**
     * แสดงข้อผิดพลาด (เช่น 404 page not found).
     *
     * @param string $menu
     * @param int    $status
     * @param string $message ข้อความที่จะแสดง ถ้าไม่กำหนดจะใช้ข้อความของระบบ
     *
     * @return \Gcms\Controller
     */
    public static function execute(\Gcms\Controller $controller, $status = 404, $message = '')
    {
        $template = Template::create($controller->menu, '', '404');
        $message = static::getMessage($message);
        $template->add(array(
            '/{TOPIC}/' => $message,
            '/{DETAIL}/' => $message,
        ));
        $controller->title = strip_tags($message);
        $controller->menu = $controller->menu;
        $controller->status = $status;
        // คืนค่า HTML

        return $template->render();
    }

    /**
     * คืนค่าข้อความ error.
     *
     * @param string $message
     *
     * @return string
     */
    private static function getMessage($message = '')
    {
        return Language::get($message == '' ? 'Sorry, cannot find a page called Please check the URL or try the call again.' : $message);
    }
}
