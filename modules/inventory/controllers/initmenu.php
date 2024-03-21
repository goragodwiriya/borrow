<?php
/**
 * @filesource modules/inventory/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Init Menu
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        // สามารถตั้งค่าระบบได้
        $submenus = [];
        if (Login::checkPermission($login, 'can_config')) {
            $submenus[] = array(
                'text' => '{LNG_Module settings}',
                'url' => 'index.php?module=inventory-settings'
            );
        }
        // สามารถบริหารจัดการได้
        if (Login::checkPermission($login, 'can_manage_inventory')) {
            $submenus[] = array(
                'text' => '{LNG_List of}',
                'url' => 'index.php?module=inventory-setup'
            );
            foreach (Language::get('INVENTORY_CATEGORIES', []) as $type => $text) {
                $submenus[] = array(
                    'text' => $text,
                    'url' => 'index.php?module=inventory-categories&amp;type='.$type
                );
            }
            $submenus[] = array(
                'text' => '{LNG_Unit}',
                'url' => 'index.php?module=inventory-categories&amp;type=unit'
            );
        }
        if (!empty($submenus)) {
            $menu->add('settings', '{LNG_Inventory}', null, $submenus, 'inventory');
        }
    }
}
