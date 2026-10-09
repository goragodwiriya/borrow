<?php
/**
 * @filesource modules/borrow/controllers/myborrow.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Myborrow;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API ตารางรายการยืม-คืนของฉัน
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
    protected $allowedSortColumns = ['borrow_no', 'topic', 'product_no', 'borrow_date', 'return_date', 'status'];

    /**
     * พารามิเตอร์เพิ่มเติมของตาราง
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return \Borrow\Report\Controller::statusParams($request);
    }

    /**
     * Query ข้อมูลสำหรับ DataTable (บังคับให้เห็นเฉพาะรายการของตัวเอง)
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        $params['borrower_id'] = $login->id;

        return \Borrow\Report\Model::toDataTable($params);
    }

    /**
     * จัดรูปแบบข้อมูลก่อนส่งให้ตาราง
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        return \Borrow\Report\Controller::formatRows($datas);
    }

    /**
     * ตัวเลือกของ filter (สถานะ)
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        return ['status' => \Borrow\Report\Controller::statusOptions()];
    }

    /**
     * แก้ไขใบยืม (ไปยังหน้าฟอร์ม)
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleEditAction(Request $request, $login)
    {
        $item = self::parseRowId($request->post('id')->toString());
        if ($item === null) {
            return $this->errorResponse('No items selected', 400);
        }

        return $this->redirectResponse('/borrow?id='.$item['borrow_id']);
    }

    /**
     * ดูรายละเอียดใบยืม (modal)
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDetailAction(Request $request, $login)
    {
        $item = self::parseRowId($request->post('id')->toString());
        if ($item === null) {
            return $this->errorResponse('No items selected', 400);
        }

        $borrow = \Borrow\Order\Model::get($item['borrow_id']);
        if (!$borrow || (int) $borrow->borrower_id !== (int) $login->id) {
            return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
        }

        return $this->successResponse([
            'data' => \Borrow\Report\Controller::detailData($borrow),
            'actions' => [
                [
                    'type' => 'modal',
                    'action' => 'open',
                    'template' => 'borrow/detail.html',
                    'title' => '{LNG_Details of} '.$borrow->borrow_no
                ]
            ]
        ]);
    }

    /**
     * ลบรายการที่เลือก (เฉพาะรายการของตัวเอง ที่ยังไม่อนุมัติ)
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::isNotDemoMode($login)) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $items = self::parseRowIds($request);
        if (empty($items)) {
            return $this->errorResponse('No items selected', 400);
        }

        $removeCount = Model::remove($items, $login->id);
        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'borrow', 'Delete', 'Delete borrow items : '.implode(', ', array_column($items, 'row_id')), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' item(s) successfully', 200, 0, 'table');
    }

    /**
     * แยก id ของแถว (รูปแบบ borrow_id_item_id) เป็น array
     * รูปแบบไม่ถูกต้องคืนค่า null
     *
     * @param string $id
     *
     * @return array|null
     */
    public static function parseRowId($id)
    {
        if (preg_match('/^([0-9]+)_([0-9]+)$/', (string) $id, $match)) {
            return [
                'row_id' => $match[0],
                'borrow_id' => (int) $match[1],
                'item_id' => (int) $match[2]
            ];
        }

        return null;
    }

    /**
     * รวบรวม id ของแถวที่เลือกทั้งหมด (ทั้งปุ่มในแถว และการเลือกหลายรายการ)
     *
     * @param Request $request
     *
     * @return array
     */
    public static function parseRowIds(Request $request)
    {
        $ids = $request->post('ids', [])->toString();
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids[] = $request->post('id')->toString();

        $items = [];
        foreach ($ids as $id) {
            $item = self::parseRowId($id);
            if ($item !== null) {
                $items[$item['row_id']] = $item;
            }
        }

        return array_values($items);
    }
}
