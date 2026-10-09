<?php
/**
 * @filesource modules/inventory/controllers/inventories.php
 *
 * api/inventory/inventories — ทะเบียนสินค้า (หน้าตั้งค่า)
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Inventory\Inventories;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * ตารางทะเบียนสินค้า
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * คอลัมน์ที่ยอมให้เรียงได้
     *
     * @var array
     */
    protected $allowedSortColumns = ['id', 'topic', 'category_id', 'model_id', 'type_id', 'stock'];

    /**
     * สิทธิ์
     *
     * @param Request $request
     * @param object  $login
     *
     * @return true|\Kotchasan\Http\Response
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_manage_inventory')) {
            return $this->errorResponse('Permission required', 403);
        }

        return true;
    }

    /**
     * ตัวกรอง
     *
     * @param Request $request
     * @param object  $login
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
     * @param array       $params
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
            $row->image = \Inventory\Product\Model::imageUrl($row->id);
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
            'type_id' => $categories->toOptions('type_id'),
            'is_active' => [
                ['value' => '1', 'text' => '{LNG_Published}'],
                ['value' => '0', 'text' => '{LNG_Unpublished}']
            ]
        ];
    }

    /**
     * ลบพัสดุที่เลือก พร้อมเลขครุภัณฑ์และข้อมูลเสริมทั้งหมด
     *
     * ถ้าลบบางตัวไม่ได้ ต้องบอกให้ชัดว่าตัวไหนและเพราะอะไร ไม่ใช่ล้มทั้งชุดเงียบ ๆ
     *
     * @param Request $request
     * @param object  $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, 'can_manage_inventory')) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        if (empty($ids)) {
            return $this->errorResponse('No data to delete', 400);
        }

        $removed = 0;
        $refused = [];
        foreach ($ids as $id) {
            try {
                if (\Inventory\Product\Model::remove($id)) {
                    ++$removed;
                }
            } catch (\Exception $e) {
                $refused[] = $e->getMessage();
            }
        }

        if ($removed === 0) {
            return $this->errorResponse(empty($refused) ? 'Delete action failed' : $refused[0], 400);
        }

        \Index\Log\Model::add(0, 'inventory', 'Delete',
            '{LNG_Delete} {LNG_Inventory} ID : '.implode(', ', $ids), $login->id);

        $message = 'Deleted '.$removed.' item(s)';
        if (!empty($refused)) {
            $message .= ' — '.$refused[0];
        }

        return $this->redirectResponse('reload', $message, 200, 0, 'table');
    }

    /**
     * เปิด/ปิดการใช้งานสินค้าจากปุ่มในแถว
     *
     * เขียน is_active กับ inuse คู่กันเสมอระหว่างเปลี่ยนผ่าน — รายงานเก่าของ
     * ผู้ใช้ยังอ่านคอลัมน์เดิมอยู่ ปล่อยให้ค้างค่าเก่าไม่ได้
     *
     * @param Request $request
     * @param object  $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleActiveAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, 'can_manage_inventory')) {
            return $this->errorResponse('Permission required', 403);
        }

        $id = $request->request('id')->toInt();
        $db = \Kotchasan\Model::createDB();
        $table = \Inventory\Base\Model::table('inventory');
        $row = $db->first($table, ['id' => $id]);
        if (!$row) {
            return $this->errorResponse('No data available', 404);
        }
        $active = empty($row->is_active) ? 1 : 0;
        $db->update($table, ['id', $id], ['is_active' => $active, 'inuse' => $active]);

        return $this->successResponse(['id' => $id, 'is_active' => $active], 'OK');
    }
}
