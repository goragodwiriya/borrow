<?php
/**
 * @filesource modules/inventory/controllers/settings.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Settings;

use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ตั้งค่าโมดูลพัสดุ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/inventory/settings/get
     * อ่านค่ากำหนดของโมดูล
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_config')) {
                return $this->errorResponse('Permission required', 403);
            }

            return $this->successResponse([
                'data' => (object) [
                    'inventory_w' => (int) self::$cfg->inventory_w,
                    // รูปแบบไฟล์ CSV ที่ทั้งการนำเข้าและส่งออกใช้ร่วมกัน
                    'csv_language' => \Inventory\Import\Model::format()['key']
                ]
            ], 'Inventory settings loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/inventory/settings/save
     * บันทึกค่ากำหนดของโมดูล
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $config = Config::load(ROOT_PATH.'settings/config.php');
            $config->inventory_w = max(100, $request->post('inventory_w')->toInt());

            // รับเฉพาะรูปแบบที่รู้จักจริง ค่าที่ไม่รู้จักตกไปที่ auto ไม่ใช่เขียนลงไปตรง ๆ
            // เพราะค่านี้ถูกส่งต่อให้ iconv() ทั้งตอนอ่านและตอนเขียนไฟล์
            $csvFormat = $request->post('csv_language')->toString();
            $config->csv_language = array_key_exists($csvFormat, \Inventory\Import\Model::formats())
                ? $csvFormat
                : 'auto';

            if (!Config::save($config, ROOT_PATH.'settings/config.php')) {
                return $this->errorResponse(Language::replace('File %s cannot be created or is read-only.', 'settings/config.php'), 500);
            }

            \Index\Log\Model::add(0, 'inventory', 'Save', 'Inventory settings saved', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
