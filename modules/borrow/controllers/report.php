<?php
/**
 * @filesource modules/borrow/controllers/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Report;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * รายงานการยืม-คืน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // สมาชิก
        $login = Login::isMember();
        // ค่าที่ส่งมา
        $params = array(
            'borrower_id' => $request->request('borrower_id')->toInt(),
            'due' => $request->request('due')->toInt(),
            'status' => $request->request('status')->toInt(),
            'borrow_status' => Language::get('BORROW_STATUS')
        );
        $params['status'] = isset($params['borrow_status'][$params['status']]) ? $params['status'] : 1;
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Borrow} &amp; {LNG_Return}');
        if ($params['status'] == 2 && $params['due'] == 1) {
            $title = Language::get('Un-Returned items');
        } else {
            $title = $params['borrow_status'][$params['status']];
        }
        $this->title .= ' '.$title;
        // เลือกเมนู
        $this->menu = 'report';
        // สามารถอนุมัติได้
        if (Login::checkPermission($login, 'can_approve_borrow')) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-exchange">{LNG_Borrow} &amp; {LNG_Return}</span></li>');
            $ul->appendChild('<li><span>{LNG_Report}</span></li>');
            $ul->appendChild('<li><span>'.$title.'</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-report">'.$this->title.'</h2>'
            ));
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'report', 'borrow'.$params['due'].$params['status']));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงตาราง
            $div->appendChild(\Borrow\Report\View::create()->render($request, $params));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
