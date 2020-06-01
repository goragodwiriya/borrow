<?php
/**
 * @filesource modules/borrow/controllers/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Settings;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตั้งค่าโมดูล Borrow.
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Module settings} {LNG_Borrow} &amp; {LNG_Return}');
        // เลือกเมนู
        $this->menu = 'settings';
        // สามารถตั้งค่าระบบได้
        if (Login::checkPermission(Login::isMember(), 'can_config')) {
            // แสดงผล
            $section = Html::create('section', array(
                'class' => 'content_bg',
            ));
            // breadcrumbs
            $breadcrumbs = $section->add('div', array(
                'class' => 'breadcrumbs',
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-settings">{LNG_Settings}</span></li>');
            $ul->appendChild('<li><span>{LNG_Borrow} &amp; {LNG_Return}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-exchange">'.$this->title.'</h2>',
            ));
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'borrow'));
            // แสดงฟอร์ม
            $section->appendChild(createClass('Borrow\Settings\View')->render());
            // คืนค่า HTML

            return $section->render();
        }
        // 404

        return \Index\Error\Controller::execute($this);
    }
}
