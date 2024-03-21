<?php
/**
 * @filesource modules/inventory/views/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Write;

use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-write&tab=product
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มเพิ่ม/แก้ไข Inventory
     *
     * @param Request $request
     * @param object $product
     *
     * @return string
     */
    public function render(Request $request, $product)
    {
        $form = Html::create('form', array(
            'id' => 'product',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/inventory/model/write/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-write',
            'title' => '{LNG_Details of} {LNG_Equipment}'
        ));
        $groups = $fieldset->add('groups');
        if ($product->id == 0) {
            // product_no
            $groups->add('text', array(
                'id' => 'product_no',
                'labelClass' => 'g-input icon-barcode',
                'itemClass' => 'width50',
                'label' => '{LNG_Serial/Registration No.}',
                'value' => isset($product->product_no) ? $product->product_no : ''
            ));
        }
        // topic
        $groups->add('text', array(
            'id' => 'topic',
            'labelClass' => 'g-input icon-edit',
            'itemClass' => 'width50',
            'label' => '{LNG_Equipment}',
            'placeholder' => '{LNG_Details of} {LNG_Equipment}',
            'value' => isset($product->topic) ? $product->topic : ''
        ));
        // category
        $category = \Inventory\Category\Model::init(false);
        $n = 0;
        foreach ($category->items() as $key => $label) {
            if ($key !== 'unit') {
                if ($n % 2 == 0) {
                    $groups = $fieldset->add('groups');
                }
                $n++;
                $groups->add('text', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-menus',
                    'itemClass' => 'width50',
                    'label' => $label,
                    'datalist' => $category->toSelect($key),
                    'value' => isset($product->{$key}) ? $product->{$key} : 0,
                    'text' => ''
                ));
            }
        }
        foreach (Language::get('INVENTORY_METAS', []) as $key => $label) {
            if ($key == 'detail') {
                $fieldset->add('textarea', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-file',
                    'itemClass' => 'item',
                    'label' => $label,
                    'rows' => 3,
                    'value' => isset($product->{$key}) ? $product->{$key} : ''
                ));
            } else {
                $fieldset->add('text', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-edit',
                    'itemClass' => 'item',
                    'label' => $label,
                    'value' => isset($product->{$key}) ? $product->{$key} : ''
                ));
            }
        }
        if ($product->id == 0) {
            $groups = $fieldset->add('groups');
            // stock
            $groups->add('number', array(
                'id' => 'stock',
                'labelClass' => 'g-input icon-number',
                'itemClass' => 'width50',
                'label' => '{LNG_Stock}',
                'value' => isset($product->stock) ? $product->stock : 1
            ));
            // unit
            $groups->add('text', array(
                'id' => 'unit',
                'labelClass' => 'g-input icon-edit',
                'itemClass' => 'width50',
                'label' => '{LNG_Unit}',
                'datalist' => $category->toSelect('unit'),
                'value' => isset($product->unit) ? $product->unit : '',
                'text' => ''
            ));
        }
        // picture
        if (is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$product->id.'.jpg')) {
            $img = WEB_URL.DATA_FOLDER.'inventory/'.$product->id.'.jpg?'.time();
        } else {
            $img = WEB_URL.'skin/img/noicon.png';
        }
        $fieldset->add('file', array(
            'id' => 'picture',
            'labelClass' => 'g-input icon-upload',
            'itemClass' => 'item',
            'label' => '{LNG_Image}',
            'comment' => '{LNG_Browse image uploaded, type :type} ({LNG_resized automatically})',
            'dataPreview' => 'imgPicture',
            'previewSrc' => $img,
            'accept' => self::$cfg->inventory_img_typies
        ));
        // inuse
        $fieldset->add('select', array(
            'id' => 'inuse',
            'labelClass' => 'g-input icon-valid',
            'itemClass' => 'item',
            'label' => '{LNG_Status}',
            'options' => Language::get('INVENTORY_STATUS'),
            'value' => $product->inuse
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $product->id
        ));
        \Gcms\Controller::$view->setContentsAfter(array(
            '/:type/' => implode(', ', self::$cfg->inventory_img_typies)
        ));
        if ($product->id == 0) {
            // Javascript
            $form->script('barcodeEnabled(["product_no"]);');
        }
        // คืนค่า HTML
        return $form->render();
    }
}
