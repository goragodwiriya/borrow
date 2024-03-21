<?php
/**
 * @filesource modules/borrow/controllers/order.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Order;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-order
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ทำรายการยืม-คืน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Transaction details} {LNG_Borrow} &amp; {LNG_Return}');
        // เลือกเมนู
        $this->menu = 'report';
        // ตรวจสอบรายการที่เลือก
        $index = \Borrow\Order\Model::get($request->request('id')->toInt());
        // สมาชิก
        $login = Login::isMember();
        // เจ้าของ และ สามารถอนุมัติได้
        if ($index && Login::checkPermission($login, 'can_approve_borrow')) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-exchange">{LNG_Borrow} &amp; {LNG_Return}</span></li>');
            $ul->appendChild('<li><span>'.$index->borrow_no.'</span></li>');
            $ul->appendChild('<li><span>{LNG_Transaction details}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
            ));
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'report', 'borrow'));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงฟอร์ม
            $div->appendChild(\Borrow\Order\View::create()->render($index));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
