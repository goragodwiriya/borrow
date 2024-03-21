<?php
/**
 * @filesource modules/borrow/views/detail.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Detail;

use Kotchasan\Date;
use Kotchasan\Language;

/**
 * module=borrow-inventory
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
        $category = \Inventory\Category\Model::init(false);
        $content = '<article class="modal_detail">';
        $content .= '<header><h3 class="icon-file cuttext">{LNG_Details of} {LNG_Equipment}</h3></header>';
        if (is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$index->id.'.jpg')) {
            $content .= '<figure class="center"><img src="'.WEB_URL.DATA_FOLDER.'inventory/'.$index->id.'.jpg"></figure>';
        }
        $content .= '<table class="border fullwidth"><tbody>';
        $content .= '<tr><th>{LNG_Equipment}</th><td>'.$index->topic.'</td></tr>';
        $content .= '<tr><th>{LNG_Serial/Registration No.}</th><td>';
        $content .= '<img style="max-width:none" src="data:image/png;base64,'.base64_encode(\Kotchasan\Barcode::create($index->product_no, 40, 9)->toPng()).'">';
        $content .= '</td></tr>';
        foreach (Language::get('INVENTORY_CATEGORIES', []) as $key => $label) {
            $content .= '<tr><th>'.$label.'</th><td>'.$category->get($key, $index->{$key}).'</td></tr>';
        }
        foreach (Language::get('INVENTORY_METAS', []) as $key => $label) {
            if ($index->{$key} != '') {
                $content .= '<tr><th>'.$label.'</th><td>'.nl2br($index->{$key}).'</td></tr>';
            }
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
        $content .= '<header><h3 class="icon-file cuttext">{LNG_Details of} '.$index->borrow_no.'</h3></header>';
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
            $content .= '<td class=topic><span class=two_lines>'.$item['topic'].' ('.$item['product_no'].')</span></td>';
            $content .= '<td class="center">'.$item['num_requests'].' '.$item['unit'].'</td>';
            $content .= '<td class="center">'.$item['amount'].' '.$item['unit'].'</td>';
            $content .= '<td class="center"><span class="term'.$item['status'].'">'.Language::get('BORROW_STATUS', null, $item['status']).'</span></td>';
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
