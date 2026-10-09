<?php
/**
 * @filesource modules/inventory/controllers/export.php
 *
 * api/inventory/export/csv — ส่งออกทะเบียนพัสดุเป็นไฟล์ CSV
 *
 * ⚠️ ไฟล์นี้เป็นรุ่นย่อสำหรับผลิตภัณฑ์ที่ไม่มีการซื้อขาย (ทะเบียนพัสดุ / ระบบยืม-คืน)
 * ของเดิมคัดลอกมาจาก oas ทั้งไฟล์ (937 บรรทัด) ซึ่งเกือบทั้งหมดเป็นการพิมพ์
 * ใบสั่งซื้อ/ใบเสร็จ/ซองจดหมาย — งานที่ผลิตภัณฑ์นี้ไม่มี และพึ่งคลาส
 * Inventory\Document / Orders / Customers ที่ถูกลบออกไปแล้ว
 *
 * เหลือไว้เฉพาะการส่งออก CSV ซึ่งเป็นคู่กับการนำเข้า (api/inventory/import)
 * ทั้งสองอ่านรายการชนิดและคอลัมน์จาก Inventory\Import\Model ที่เดียว
 *
 * ผลิตภัณฑ์ที่ขายของ (oas / oms.in.th) ยังใช้ไฟล์เต็มตามเดิม
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Export;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * ส่งออกข้อมูลของโมดูล inventory
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Export\Export\Controller
{
    /**
     * GET api/inventory/export/csv?type=<ชนิด>
     *
     * ชนิดที่ส่งออกได้ตรงกับชนิดที่นำเข้าได้เสมอ เพราะอ่านจาก Import\Model
     * ที่เดียว ไฟล์ที่ส่งออกจึงนำกลับเข้าระบบได้โดยไม่ต้องแก้หัวคอลัมน์
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response|void
     */
    public function csv(Request $request)
    {
        $login = $this->authenticateRequest($request);
        if (!$login) {
            return $this->errorResponse('Unauthorized', 401);
        }
        if (!ApiController::hasPermission($login, 'can_manage_inventory')) {
            return $this->errorResponse('Permission required', 403);
        }

        $type = $request->get('type')->filter('a-z');
        $spec = \Inventory\Import\Model::type($type);
        if ($spec === null) {
            return $this->errorResponse('No data available', 404);
        }

        // รูปแบบไฟล์ตามที่ตั้งไว้ในหน้าตั้งค่าโมดูล ค่าเดียวกับที่ฝั่งนำเข้าใช้
        // ('auto' ตรวจไฟล์ไม่ได้ตอนส่งออก จึงเทียบเท่า UTF-8 with BOM)
        $format = \Inventory\Import\Model::format()['export'];

        // ส่ง header แล้วจบการทำงานเอง จึงไม่มี Response กลับไป
        self::sendCsv($type, $spec['columns'], \Inventory\Import\Model::rows($type),
            $format['charset'], $format['bom']);
    }
}
