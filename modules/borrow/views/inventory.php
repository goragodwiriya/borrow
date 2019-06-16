<?php
/**
 * @filesource modules/borrow/views/inventory.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
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
     * @var mixed
     */
    private $params = array();
    private $categories;

    /**
     * ตารางรายชื่อสมาชิก
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $fields = array('id', 'equipment', 'serial');
        $headers = array(
            'equipment' => array(
                'text' => '{LNG_Equipment}',
                'sort' => 'equipment',
            ),
            'serial' => array(
                'text' => '{LNG_Serial/Registration number}',
                'sort' => 'serial',
            ),
        );
        $cols = array();
        $filters = array();
        $this->categories = \Inventory\Category\Model::init();
        foreach (Language::get('INVENTORY_CATEGORIES') as $type => $text) {
            $this->params[] = $type;
            $fields[] = $type;
            $headers[$type] = array(
                'text' => $text,
                'class' => 'center',
            );
            $cols[$type] = array('class' => 'center');
            $filters[$type] = array(
                'name' => $type,
                'default' => 0,
                'text' => $text,
                'options' => array(0 => '{LNG_all items}') + $this->categories->toSelect($type),
                'value' => $request->request($type)->toInt(),
            );
        }
        $fields[] = '"" picture';
        $headers['picture'] = array(
            'text' => '{LNG_Image}',
            'class' => 'center',
        );
        $cols['picture'] = array('class' => 'center');
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Borrow\Inventory\Model::toDataTable(),
            /* ฟิลด์ที่กำหนด (หากแตกต่างจาก Model) */
            'fields' => $fields,
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('borrow_inventory_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('borrow_inventory_sort', 'id desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('equipment', 'serial'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/borrow/model/inventory/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => $headers,
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => $cols,
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'detail' => array(
                    'class' => 'icon-info button orange',
                    'id' => ':id',
                    'text' => '{LNG_Detail}',
                ),

            ),
        ));
        // save cookie
        setcookie('borrow_inventory_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('borrow_inventory_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML

        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว.
     *
     * @param array $item
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'inventory/'.$item['id'].'.jpg' : WEB_URL.'modules/inventory/img/noimage.png';
        $item['picture'] = '<img src="'.$thumb.'" style="max-height:50px;max-width:50px" alt=thumbnail>';
        foreach ($this->params as $key) {
            $item[$key] = $this->categories->get($key, $item[$key]);
        }

        return $item;
    }
}
