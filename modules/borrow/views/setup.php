<?php
/**
 * @filesource modules/borrow/views/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Setup;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * module=borrow-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * รายงานการยืม-คืน
     *
     * @param Request $request
     * @param array $params
     *
     * @return string
     */
    public function render(Request $request, $params)
    {
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Borrow\Setup\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('borrowSetup_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('borrowSetup_sort', 'id desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('borrow_id', 'id', 'product_no', 'amount', 'returned_amount', 'due', 'status', 'count'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic', 'product_no', 'borrow_no'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/borrow/model/setup/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'borrow_no' => array(
                    'text' => '{LNG_No.}',
                    'sort' => 'borrow_no'
                ),
                'topic' => array(
                    'text' => '{LNG_Equipment}',
                    'sort' => 'topic'
                ),
                'num_requests' => array(
                    'text' => '{LNG_Quantity}',
                    'class' => 'center'
                ),
                'borrow_date' => array(
                    'text' => '{LNG_Borrowed date}',
                    'sort' => 'borrow_date',
                    'class' => 'center'
                ),
                'return_date' => array(
                    'text' => '{LNG_Date of return}',
                    'sort' => 'return_date',
                    'class' => 'center'
                ),
                'delivery_date' => array(
                    'text' => '{LNG_Delivery} ({LNG_Quantity})',
                    'sort' => 'delivery_date',
                    'class' => 'center'
                ),
                'returned_date' => array(
                    'text' => '{LNG_Returned} ({LNG_Quantity})',
                    'sort' => 'returned_date',
                    'class' => 'center'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'borrow_no' => array(
                    'class' => 'nowrap'
                ),
                'topic' => array(
                    'class' => 'topic'
                ),
                'num_requests' => array(
                    'class' => 'center'
                ),
                'borrow_date' => array(
                    'class' => 'center nowrap'
                ),
                'return_date' => array(
                    'class' => 'center nowrap'
                ),
                'amount' => array(
                    'class' => 'center'
                ),
                'delivery_date' => array(
                    'class' => 'center nowrap'
                ),
                'returned_date' => array(
                    'class' => 'center nowrap'
                )
            ),
            /* ฟังก์ชั่นตรวจสอบการแสดงผลปุ่มในแถว */
            'onCreateButton' => array($this, 'onCreateButton'),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'delete' => array(
                    'class' => 'icon-delete button red',
                    'id' => ':id_:borrow_id',
                    'text' => '{LNG_Delete}'
                ),
                'edit' => array(
                    'class' => 'icon-edit button green',
                    'href' => $uri->createBackUri(array('module' => 'borrow', 'id' => ':borrow_id')),
                    'text' => '{LNG_Edit}'
                ),
                'detail' => array(
                    'class' => 'icon-info button orange',
                    'id' => ':borrow_id',
                    'text' => '{LNG_Detail}'
                )
            ),
            /* ปุ่มเพิ่ม */
            'addNew' => array(
                'class' => 'float_button icon-new',
                'href' => 'index.php?module=borrow',
                'title' => '{LNG_Add Borrow}'
            )
        ));
        // save cookie
        setcookie('borrowSetup_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('borrowSetup_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
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
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['borrow_no'] = '<a href="index.php?module=borrow-setup&amp;status='.$item['status'].'&amp;search='.$item['borrow_no'].'">'.$item['borrow_no'].'</a>';
        $item['topic'] = '<a class=two_lines href="index.php?module=borrow-setup&amp;status='.$item['status'].'&amp;search='.$item['product_no'].'">'.$item['topic'].'</a>';
        $item['borrow_date'] = Date::format($item['borrow_date'], 'd M Y');
        $item['return_date'] = Date::format($item['return_date'], 'd M Y');
        if ($item['return_date'] != '' && $item['status'] == 2 && $item['due'] <= 0) {
            $item['return_date'] = '<span class="term3">'.$item['return_date'].'</span>';
        }
        return $item;
    }

    /**
     * ฟังก์ชั่นตรวจสอบว่าสามารถสร้างปุ่มได้หรือไม่
     *
     * @param string $btn ID ของปุ่ม
     * @param array $attributes Property ของปุ่ม
     * @param array $item ข้อมูล
     *
     * @return array|bool
     */
    public function onCreateButton($btn, $attributes, $item)
    {
        if ($btn == 'edit') {
            return $item['count'] === null ? $attributes : false;
        } elseif ($btn == 'delete') {
            return $item['status'] === 0 || $item['status'] === 1 ? $attributes : false;
        } else {
            return $attributes;
        }
    }
}
