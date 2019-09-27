<?php
/**
 * @filesource modules/borrow/views/detail.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Detail;

use Kotchasan\Date;
use Kotchasan\Language;

/**
 * module=borrow-inventory.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงรายละเอียด พัสดุ
     *
     * @param object $index
     *
     * @return string
     */
    public static function details($index)
    {
        $content = '<article class="modal_detail">';
        $content .= '<header><h1 class="cuttext">{LNG_Details of} {LNG_Equipment}</h1></header>';
        if (is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$index->id.'.jpg')) {
            $content .= '<figure class="center"><img src="'.WEB_URL.DATA_FOLDER.'inventory/'.$index->id.'.jpg"></figure>';
        }
        $content .= '<table class="border fullwidth"><tbody>';
        $content .= '<tr><th>{LNG_Equipment}</th><td>'.$index->equipment.'</td></tr>';
        $content .= '<tr><th>{LNG_Serial/Registration number}</th><td>'.$index->serial.'</td></tr>';
        foreach (Language::get('INVENTORY_CATEGORIES', array()) as $key => $label) {
            $content .= '<tr><th>'.$label.'</th><td>'.$index->{$key}.'</td></tr>';
        }
        if ($index->detail != '') {
            $content .= '<tr><th>{LNG_Detail}</th><td>'.nl2br($index->detail).'</td></tr>';
        }
        $content .= '<tr><th>{LNG_Remain}</th><td>'.$index->stock.' '.$index->unit.'</td></tr>';
        $content .= '</tbody></article>';
        $content .= '</article>';
        // คืนค่า HTML

        return Language::trans($content);
    }

    /**
     * แสดงรายละเอียด ยืม - คืน
     *
     * @param object $index
     *
     * @return string
     */
    public static function render($index)
    {
        $content = '<article class="modal_detail">';
        $content .= '<header><h1 class="cuttext">{LNG_Details of} '.$index->borrow_no.'</h1></header>';
        $content .= '<table class="border fullwidth"><tbody>';
        $content .= '<tr><th>{LNG_Borrower}</th><td class="status'.$index->status.'">'.$index->borrower.'</td></tr>';
        $content .= '<tr><th>{LNG_Transaction date}</th><td>'.Date::format($index->transaction_date, 'd M Y').'</td></tr>';
        $content .= '<tr><th>{LNG_Borrowed date}</th><td>'.Date::format($index->borrow_date, 'd M Y').'</td></tr>';
        $content .= '<tr><th>{LNG_Date of return}</th><td>'.Date::format($index->return_date, 'd M Y').'</td></tr>';
        $content .= '</tbody></table>';
        $content .= '<div class="tablebody">';
        $content .= '<table class="fullwidth data border margin-top"><thead><tr>';
        $content .= '<th>{LNG_Detail}</th>';
        $content .= '<th>{LNG_Quantity}</th>';
        $content .= '<th>{LNG_Delivery}</th>';
        $content .= '<th>{LNG_Status}</th>';
        $content .= '</tr></thead><tbody>';
        foreach (\Borrow\Order\Model::items($index->id) as $item) {
            $content .= '<tr>';
            $content .= '<td>'.$item['topic'].'</td>';
            $content .= '<td class="center">'.$item['num_requests'].' '.$item['unit'].'</td>';
            $content .= '<td class="center">'.$item['amount'].'</td>';
            $content .= '<td class="center"><span class="term'.$item['status'].'">'.Language::find('BORROW_STATUS', null, $item['status']).'</span></td>';
            $content .= '</tr>';
        }
        $content .= '</tbody>';
        $content .= '</table>';
        $content .= '</div>';
        $content .= '</article>';
        // คืนค่า HTML

        return Language::trans($content);
    }
}
