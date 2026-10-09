<?php
/**
 * @filesource modules/borrow/controllers/autocomplete.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Autocomplete;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ค้นหาข้อมูลสำหรับฟอร์มของโมดูลยืม-คืน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/borrow/autocomplete/find
     * ค้นหาพัสดุที่ยืมได้ จากชื่อพัสดุหรือเลขครุภัณฑ์ (สำหรับ data-autocomplete)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function find(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $search = $request->get('q')->topic();
            if ($search === '') {
                $search = $request->get('search')->topic();
            }
            $limit = min(50, max(1, $request->get('limit', 20)->toInt()));

            // Autocomplete ฝั่ง Now.js อ่านรายการจาก response.data โดยตรง
            return $this->successResponse(\Borrow\Inventory\Model::search($search, $limit), 'Search completed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * GET api/borrow/autocomplete/product
     * อ่านรายละเอียดพัสดุจากเลขครุภัณฑ์ที่แน่นอน (data-detail-api ของ LineItemsManager)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function product(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $product_no = $request->get('product')->topic();
            if ($product_no === '') {
                $product_no = $request->get('value')->topic();
            }

            $data = $product_no === '' ? null : \Borrow\Inventory\Model::find($product_no);
            if ($data === null) {
                return $this->errorResponse(Language::replace('Sorry, :name not found It&#39;s may be deleted', [':name' => $product_no]), 404);
            }

            // จำนวนที่กรอกในช่องข้างๆ (data-role="quantity") อย่างน้อย 1
            $data['quantity'] = max(1, $request->get('quantity', 1)->toInt());

            return $this->successResponse($data, 'Inventory found');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * GET api/borrow/autocomplete/user
     * ค้นหาสมาชิกสำหรับเปลี่ยนผู้ยืม (เฉพาะเจ้าหน้าที่ที่อนุมัติได้)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function user(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_approve_borrow')) {
                return $this->errorResponse('Permission required', 403);
            }

            $search = $request->get('q')->topic();
            if ($search === '') {
                return $this->successResponse([], 'Search completed');
            }

            $keyword = '%'.$search.'%';
            $result = \Kotchasan\Model::createQuery()
                ->select('id', 'name', 'username')
                ->from('user')
                ->where([['active', 1]])
                ->where([
                    ['name', 'LIKE', $keyword],
                    ['username', 'LIKE', $keyword],
                    ['phone', 'LIKE', $keyword]
                ], 'OR')
                ->orderBy('name')
                ->limit(min(50, max(1, $request->get('limit', 20)->toInt())))
                ->fetchAll(true);

            $datas = [];
            foreach ($result as $item) {
                $datas[] = [
                    'value' => $item['id'],
                    'text' => $item['name'].' ('.$item['username'].')',
                    'name' => $item['name']
                ];
            }

            return $this->successResponse($datas, 'Search completed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
