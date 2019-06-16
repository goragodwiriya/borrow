<?php
/**
 * @filesource modules/borrow/controllers/inventory.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-inventory.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตารางรายการ พัสดุ
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_List of} {LNG_Inventory}');
        // เลือกเมนู
        $this->menu = 'inventory';
        // แสดงผล
        $section = Html::create('section', array(
            'class' => 'content_bg',
        ));
        // breadcrumbs
        $breadcrumbs = $section->add('div', array(
            'class' => 'breadcrumbs',
        ));
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-product">{LNG_Inventory}</span></li>');
        $ul->appendChild('<li><span>{LNG_List of}</span></li>');
        $section->add('header', array(
            'innerHTML' => '<h2 class="icon-list">'.$this->title.'</h2>',
        ));
        // แสดงตาราง
        $section->appendChild(createClass('Borrow\Inventory\View')->render($request));
        // คืนค่า HTML

        return $section->render();
    }
}
