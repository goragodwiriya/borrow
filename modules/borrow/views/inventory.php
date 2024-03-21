<?php
/**
 * @filesource modules/borrow/views/inventory.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
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
     * @var object
     */
    private $category;
    /**
     * @var array
     */
    private $inventory_status;

    /**
     * ตาราง Inventory
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $this->inventory_status = Language::get('INVENTORY_STATUS');
        $params = array(
            'category_id' => $request->request('category_id')->toInt(),
            'type_id' => $request->request('type_id')->toInt(),
            'model_id' => $request->request('model_id')->toInt()
        );
        $this->category = \Inventory\Category\Model::init(false);
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Borrow\Inventory\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('borrow_inventory_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('borrow_inventory_sort', 'id desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('unit'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('product_no', 'topic'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/borrow/model/inventory/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => array(
                array(
                    'name' => 'category_id',
                    'text' => '{LNG_Category}',
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect('category_id'),
                    'value' => $params['category_id']
                ),
                array(
                    'name' => 'type_id',
                    'text' => '{LNG_Type}',
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect('type_id'),
                    'value' => $params['type_id']
                ),
                array(
                    'name' => 'model_id',
                    'text' => '{LNG_Model}',
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect('model_id'),
                    'value' => $params['model_id']
                )
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'id' => array(
                    'text' => '{LNG_Image}',
                    'sort' => 'id'
                ),
                'topic' => array(
                    'text' => '{LNG_Equipment}',
                    'sort' => 'topic'
                ),
                'product_no' => array(
                    'text' => '{LNG_Serial/Registration No.}',
                    'sort' => 'product_no'
                ),
                'category_id' => array(
                    'text' => '{LNG_Category}',
                    'class' => 'center',
                    'sort' => 'category_id'
                ),
                'type_id' => array(
                    'text' => '{LNG_Type}',
                    'class' => 'center',
                    'sort' => 'type_id'
                ),
                'model_id' => array(
                    'text' => '{LNG_Model}',
                    'class' => 'center',
                    'sort' => 'model_id'
                ),
                'stock' => array(
                    'text' => '{LNG_Stock}',
                    'class' => 'center',
                    'sort' => 'stock'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'topic' => array(
                    'class' => 'topic'
                ),
                'product_no' => array(
                    'class' => 'nowrap'
                ),
                'category_id' => array(
                    'class' => 'center nowrap'
                ),
                'type_id' => array(
                    'class' => 'center nowrap'
                ),
                'model_id' => array(
                    'class' => 'center nowrap'
                ),
                'stock' => array(
                    'class' => 'center'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'detail' => array(
                    'class' => 'icon-info button orange',
                    'id' => ':product_no',
                    'text' => '{LNG_Detail}'
                )
            )
        ));
        // save cookie
        setcookie('borrow_inventory_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('borrow_inventory_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array คืนค่า $item กลับไป
     */
    public function onRow($item, $o, $prop)
    {
        $item['product_no'] = '<img style="max-width:none" src="data:image/png;base64,'.base64_encode(\Kotchasan\Barcode::create($item['product_no'], 40, 9)->toPng()).'">';
        $item['topic'] = '<span class=two_lines>'.$item['topic'].'</span>';
        $item['category_id'] = $this->category->get('category_id', $item['category_id']);
        $item['type_id'] = $this->category->get('type_id', $item['type_id']);
        $item['model_id'] = $this->category->get('model_id', $item['model_id']);
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'inventory/'.$item['id'].'.jpg' : WEB_URL.'skin/img/noicon.png';
        $item['stock'] .= ' '.$item['unit'];
        $item['id'] = '<img src="'.$thumb.'" style="max-height:50px;max-width:50px" alt=thumbnail>';
        return $item;
    }
}
