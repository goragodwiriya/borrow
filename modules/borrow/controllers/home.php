<?php
/**
 * @filesource modules/borrow/controllers/home.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Home;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API สถิติสำหรับการ์ดสรุปของโมดูลยืม-คืน (data-component="api")
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/borrow/home
     * อ่านจำนวนรายการแยกตามสถานะ
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $datas = Model::get($login, ApiController::hasPermission($login, 'can_approve_borrow'));

            return $this->successResponse($datas, 'Statistics retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
