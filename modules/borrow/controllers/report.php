<?php
/**
 * @filesource modules/borrow/controllers/report.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
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
        $index = (object) array(
            'borrower_id' => $request->request('borrower_id')->toInt(),
            'due' => $request->request('due')->toInt(),
            'status' => $request->request('status')->toInt(),
            'borrow_status' => Language::get('BORROW_STATUS'),
        );
        $index->status = isset($index->borrow_status[$index->status]) ? $index->status : 1;
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Borrow} &amp; {LNG_Return}');
        if ($index->status == 2 && $index->due == 1) {
            $title = Language::get('Un-Returned items');
        } else {
            $title = $index->borrow_status[$index->status];
        }
        $this->title .= ' '.$title;
        // เลือกเมนู
        $this->menu = 'report';
        // สามารถอนุมัติได้
        if (Login::checkPermission(Login::isMember(), 'can_approve_borrow')) {
            // แสดงผล
            $section = Html::create('section', array(
                'class' => 'content_bg',
            ));
            // breadcrumbs
            $breadcrumbs = $section->add('div', array(
                'class' => 'breadcrumbs',
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-exchange">{LNG_Borrow} &amp; {LNG_Return}</span></li>');
            $ul->appendChild('<li><span>{LNG_Report}</span></li>');
            $ul->appendChild('<li><span>'.$title.'</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-report">'.$this->title.'</h2>',
            ));
            // แสดงตาราง
            $section->appendChild(createClass('Borrow\Report\View')->render($request, $index));
            // คืนค่า HTML

            return $section->render();
        }
        // 404

        return \Index\Error\Controller::execute($this);
    }
}
