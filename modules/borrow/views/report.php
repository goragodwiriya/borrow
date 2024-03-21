<?php
/**
 * @filesource modules/borrow/views/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Report;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * module=borrow-report
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
            'model' => \Borrow\Report\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('borrowReport_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('borrowReport_sort', 'borrow_date DESC,id')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id', 'product_no', 'Ustatus', 'borrower_id', 'amount', 'returned_amount', 'due', 'status', 'count_stock'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('borrow_no', 'borrower', 'product_no'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/borrow/model/report/action',
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
                'stock' => array(
                    'text' => '{LNG_Stock}',
                    'sort' => 'stock',
                    'class' => 'center'
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
                'borrower' => array(
                    'text' => '{LNG_Borrower}',
                    'sort' => 'borrower_id',
                    'class' => 'center'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'stock' => array(
                    'class' => 'center'
                ),
                'num_requests' => array(
                    'class' => 'center'
                ),
                'borrow_date' => array(
                    'class' => 'center'
                ),
                'return_date' => array(
                    'class' => 'center'
                ),
                'borrower' => array(
                    'class' => 'center'
                ),
                'amount' => array(
                    'class' => 'center'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'edit' => array(
                    'class' => 'icon-list button green',
                    'href' => $uri->createBackUri(array('module' => 'borrow-order', 'id' => ':id')),
                    'text' => '{LNG_Detail}'
                )
            )
        ));
        if ($params['status'] == 0 || $params['status'] == 1) {
            $table->actions[] = array(
                'id' => 'action',
                'class' => 'ok',
                'text' => '{LNG_With selected}',
                'options' => array(
                    'delete' => '{LNG_Delete}'
                )
            );
        }
        // save cookie
        setcookie('borrowReport_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('borrowReport_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
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
        $item['borrow_no'] = '<a href="index.php?module=borrow-report&amp;status='.$item['status'].'&amp;search='.$item['borrow_no'].'">'.$item['borrow_no'].'</a>';
        $item['topic'] = '<a href="index.php?module=borrow-report&amp;status='.$item['status'].'&amp;search='.$item['product_no'].'">'.$item['topic'].'</a>';
        $item['borrower'] = '<a href="index.php?module=borrow-report&amp;status='.$item['status'].'&amp;borrower_id='.$item['borrower_id'].'" class="status'.$item['Ustatus'].'">'.$item['borrower'].'</a>';
        $item['borrow_date'] = Date::format($item['borrow_date'], 'd M Y');
        $item['return_date'] = Date::format($item['return_date'], 'd M Y');
        if ($item['status'] == 2 && $item['due'] <= 0 && $item['return_date'] != '') {
            $item['return_date'] = '<span class="term3">'.$item['return_date'].'</span>';
        }
        $item['stock'] = empty($item['count_stock']) ? '{LNG_Unlimited}' : number_format($item['stock']);
        $item['num_requests'] = number_format($item['num_requests']);
        return $item;
    }
}
