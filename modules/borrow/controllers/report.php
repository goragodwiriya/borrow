<?php
/**
 * @filesource modules/borrow/controllers/report.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Report;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ตารางรายงานการยืม-คืนทั้งหมด (สำหรับเจ้าหน้าที่)
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
    protected $allowedSortColumns = ['borrow_no', 'topic', 'product_no', 'borrower', 'borrow_date', 'return_date', 'status'];

    /**
     * ตรวจสอบสิทธิ์ (ต้องเป็นผู้ที่อนุมัติได้)
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_approve_borrow')) {
            return $this->errorResponse('Permission required', 403);
        }

        return true;
    }

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
        $params = self::statusParams($request);
        $params['borrower_id'] = $request->get('borrower_id')->toInt();

        return $params;
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
     * จัดรูปแบบข้อมูลก่อนส่งให้ตาราง
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        return self::formatRows($datas);
    }

    /**
     * ตัวเลือกของ filter (สถานะ และผู้ยืม)
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        $borrowers = [];
        $query = \Kotchasan\Model::createQuery()
            ->select('U.id', 'U.name')
            ->from('borrow W')
            ->join('user U', [['U.id', 'W.borrower_id']], 'INNER')
            ->groupBy('U.id')
            ->orderBy('U.name')
            ->fetchAll(true);
        foreach ($query as $item) {
            $borrowers[] = ['value' => $item['id'], 'text' => $item['name']];
        }

        return [
            'status' => self::statusOptions(),
            'borrower_id' => $borrowers
        ];
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
        $item = \Borrow\Myborrow\Controller::parseRowId($request->post('id')->toString());
        if ($item === null) {
            return $this->errorResponse('No items selected', 400);
        }

        $borrow = \Borrow\Order\Model::get($item['borrow_id']);
        if (!$borrow) {
            return $this->errorResponse('Sorry, Item not found It&#39;s may be deleted', 404);
        }

        return $this->successResponse([
            'data' => self::detailData($borrow),
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
     * เปิดหน้าตรวจสอบ/ส่งมอบของเจ้าหน้าที่
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleEditAction(Request $request, $login)
    {
        $item = \Borrow\Myborrow\Controller::parseRowId($request->post('id')->toString());
        if ($item === null) {
            return $this->errorResponse('No items selected', 400);
        }

        return $this->redirectResponse('/borrow-order?id='.$item['borrow_id']);
    }

    /**
     * ลบรายการที่เลือก (เฉพาะรายการที่ยังไม่อนุมัติ)
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_approve_borrow'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $items = \Borrow\Myborrow\Controller::parseRowIds($request);
        if (empty($items)) {
            return $this->errorResponse('No items selected', 400);
        }

        $removeCount = \Borrow\Myborrow\Model::remove($items);
        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'borrow', 'Delete', 'Delete borrow items : '.implode(', ', array_column($items, 'row_id')), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' item(s) successfully', 200, 0, 'table');
    }

    /**
     * อ่านพารามิเตอร์สถานะและกำหนดคืน ใช้ร่วมกันระหว่างตารางของฉันและตารางรายงาน
     *
     * @param Request $request
     *
     * @return array
     */
    public static function statusParams(Request $request)
    {
        $status = $request->get('status', 0)->toInt();
        $borrow_status = Language::get('BORROW_STATUS', []);

        return [
            'status' => array_key_exists($status, $borrow_status) ? $status : 0,
            // due=1 คือแสดงเฉพาะรายการที่ครบกำหนดคืนแล้ว (ใช้กับสถานะอนุมัติเท่านั้น)
            'due' => $request->get('due')->toInt()
        ];
    }

    /**
     * ตัวเลือกสถานะของใบยืม
     *
     * @return array
     */
    public static function statusOptions()
    {
        $options = [];
        foreach (Language::get('BORROW_STATUS', []) as $value => $text) {
            $options[] = ['value' => $value, 'text' => $text];
        }

        return $options;
    }

    /**
     * จัดรูปแบบแถวของตารางยืม-คืน (ใช้ร่วมกันทั้งตารางของฉันและตารางรายงาน)
     *
     * @param array $datas
     *
     * @return array
     */
    public static function formatRows(array $datas)
    {
        foreach ($datas as $item) {
            // สต็อกไม่จำกัดแสดงเป็นข้อความ
            if (property_exists($item, 'count_stock')) {
                $item->stock_text = empty($item->count_stock)
                    ? Language::get('Unlimited')
                    : \Kotchasan\Number::format($item->stock);
            }
            // ส่งมอบแล้ว / ที่ยืม
            $item->amount_text = \Kotchasan\Number::format($item->amount).' / '.\Kotchasan\Number::format($item->num_requests);
            // ครบกำหนดคืนแล้วหรือยัง (ใช้ระบายสีคอลัมน์กำหนดคืน)
            $item->overdue = ((int) $item->status === 2 && $item->return_date !== null && (int) $item->due <= 0) ? 1 : 0;
        }

        return $datas;
    }

    /**
     * ข้อมูลสำหรับ modal รายละเอียดใบยืม
     *
     * @param object $borrow
     *
     * @return array
     */
    public static function detailData($borrow)
    {
        $data = (array) $borrow;
        $borrow_status = Language::get('BORROW_STATUS', []);

        $items = [];
        foreach (\Borrow\Order\Model::items($borrow->id) as $item) {
            $item['status_text'] = isset($borrow_status[$item['status']]) ? $borrow_status[$item['status']] : $item['status'];
            $items[] = $item;
        }
        $data['items'] = $items;

        return $data;
    }
}
