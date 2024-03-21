<?php
/**
 * @filesource modules/borrow/views/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Index;

use Kotchasan\Html;

/**
 * module=borrow
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์ม ยืมพัสดุ
     *
     * @param object $index
     * @param array $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $form = Html::create('form', array(
            'id' => 'order_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/borrow/model/index/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Transaction details}',
            'titleClass' => 'icon-cart'
        ));
        $groups = $fieldset->add('groups');
        // borrow_no
        $groups->add('text', array(
            'id' => 'borrow_no',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Transaction No.}',
            'placeholder' => '{LNG_Leave empty for generate auto}',
            'value' => $index->borrow_no,
            'readonly' => true
        ));
        // transaction_date
        $groups->add('date', array(
            'id' => 'transaction_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Transaction date}',
            'value' => $index->transaction_date,
            'readonly' => true
        ));
        $groups = $fieldset->add('groups');
        // borrow_date
        $groups->add('date', array(
            'id' => 'borrow_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Borrowed date}',
            'value' => $index->borrow_date
        ));
        // return_date
        $groups->add('date', array(
            'id' => 'return_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Date of return}',
            'value' => $index->return_date
        ));
        $groups = $fieldset->add('groups');
        // inventory_quantity
        $groups->add('number', array(
            'id' => 'inventory_quantity',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width20',
            'label' => '{LNG_Quantity}',
            'value' => 1
        ));
        // inventory
        $groups->add('text', array(
            'id' => 'inventory',
            'labelClass' => 'g-input icon-barcode',
            'itemClass' => 'width80',
            'label' => '{LNG_Equipment}/{LNG_Serial/Registration No.}',
            'title' => '{LNG_Equipment}',
            'placeholder' => '{LNG_Find equipment by} {LNG_Equipment}, {LNG_Serial/Registration No.}'
        ));
        $table = '<table class="fullwidth"><thead><tr>';
        $table .= '<th>{LNG_Detail}</th>';
        $table .= '<th>{LNG_Serial/Registration No.}</th>';
        $table .= '<th class=center>{LNG_Quantity}</th>';
        $table .= '<th class=center>{LNG_Unit}</th>';
        $table .= '<th></th>';
        $table .= '</tr></thead><tbody id=tb_products>';
        foreach (\Borrow\Index\Model::items($index->id) as $item) {
            $table .= '<tr'.($index->id == 0 ? ' class=hidden' : '').'>';
            $table .= '<td><label class="g-input"><input type=text name=topic[] value="'.$item['topic'].'" readonly></label></td>';
            $table .= '<td><label class="g-input"><input type=text name=product_no[] value="'.$item['product_no'].'" readonly></label></td>';
            $table .= '<td><label class="g-input"><input type=text name=quantity[] size=2 value="'.$item['quantity'].'" max="'.(empty($item['count_stock']) ? 2147483647 : $item['stock']).'" class="num"></label></td>';
            $table .= '<td><label class="g-input"><input type=text name=unit[] size="5" value="'.$item['unit'].'" readonly></label></td>';
            $table .= '<td><a class="button wide delete notext"><span class=icon-delete></span></a></td>';
            $table .= '</tr>';
        }
        $table .= '</tbody>';
        $table .= '</table>';
        $fieldset->add('div', array(
            'class' => 'item',
            'innerHTML' => $table
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit right'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button ok large',
            'id' => 'order_submit',
            'value' => '{LNG_Save}'
        ));
        // borrow_id
        $fieldset->add('hidden', array(
            'id' => 'borrow_id',
            'value' => $index->id
        ));
        // Javascript
        $form->script('initBorrowIndex();');
        // คืนค่า HTML
        return $form->render();
    }
}
