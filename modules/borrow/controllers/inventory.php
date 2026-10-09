<?php
/**
 * @filesource modules/borrow/controllers/inventory.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Inventory;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ตารางคลังพัสดุ (มุมมองของผู้ยืม แสดงเฉพาะพัสดุที่เปิดใช้งาน)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * คอลัมน์ที่เรียงลำดับได้ (ป้องกัน SQL injection)
     *
     * @var array
     */
    protected $allowedSortColumns = ['id', 'topic', 'product_no', 'stock'];

    /**
     * พารามิเตอร์เพิ่มเติมของตาราง (หมวดหมู่)
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'category_id' => $request->get('category_id')->topic(),
            'model_id' => $request->get('model_id')->topic(),
            'type_id' => $request->get('type_id')->topic(),
            'is_active' => $request->get('is_active')->toString()
        ];
    }

    /**
     * Query ข้อมูลสำหรับ DataTable
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params);
    }

    /**
     * Format user list with additional display fields
     *
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $data = [];
        foreach ($datas as $row) {
            // id ของแถวคือเลขครุภัณฑ์ รูปภาพผูกอยู่กับพัสดุ จึงใช้ inventory_id
            $row->image = \Inventory\Product\Model::imageUrl($row->inventory_id);
            $data[] = $row;
        }
        return $data;
    }

    /**
     * ตัวเลือกของ filter (หมวดหมู่ ประเภท ยี่ห้อ)
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        $categories = \Inventory\Category\Controller::init();

        return [
            'category_id' => $categories->toOptions('category_id'),
            'model_id' => $categories->toOptions('model_id'),
            'type_id' => $categories->toOptions('type_id')
        ];
    }

    /**
     * ดูรายละเอียดพัสดุ (modal)
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDetailAction(Request $request, $login)
    {
        $product_no = $request->post('id')->topic();
        $search = $product_no === '' ? null : Model::get($product_no);
        if (!$search) {
            return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
        }

        return $this->successResponse([
            'data' => self::detailData($search),
            'actions' => [
                [
                    'type' => 'modal',
                    'action' => 'open',
                    'template' => 'borrow/inventorydetail.html',
                    'title' => '{LNG_Details of} '.$search->topic
                ]
            ]
        ]);
    }

    /**
     * ยืมพัสดุรายการนี้ (ไปยังฟอร์มทำรายการยืม)
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleBorrowAction(Request $request, $login)
    {
        return $this->redirectResponse('/borrow?product_no='.rawurlencode($request->post('id')->topic()));
    }

    /**
     * ข้อมูลสำหรับ modal รายละเอียดพัสดุ
     *
     * @param object $search
     *
     * @return array
     */
    public static function detailData($search)
    {
        $data = (array) $search;
        $category = \Inventory\Category\Controller::init();

        // ชื่อหมวดหมู่ (แสดงเป็นรายการ label/value เพื่อให้เทมเพลตวนแสดงได้)
        $categories = [];
        foreach (\Inventory\Category\Controller::items() as $key => $label) {
            if ($key !== 'unit') {
                $categories[] = [
                    'label' => Language::trans($label),
                    'value' => $category->get($key, $search->{$key}, '-')
                ];
            }
        }
        $data['categories'] = $categories;

        // ข้อมูลเพิ่มเติม (meta)
        $metas = [];
        foreach (\Inventory\Product\Model::metas() as $key => $label) {
            if (!empty($search->{$key})) {
                $metas[] = [
                    'label' => Language::trans($label),
                    'value' => \Kotchasan\Text::untextarea($search->{$key})
                ];
            }
        }
        $data['metas'] = $metas;

        $data['image'] = \Inventory\Product\Model::imageUrl((int) $search->id);
        $data['barcode'] = \Inventory\Items\Model::barcodeImage($search->product_no);
        $data['stock_text'] = empty($search->count_stock)
            ? Language::get('Unlimited')
            : trim(\Kotchasan\Number::format($search->stock).' '.$search->unit);

        return $data;
    }
}
