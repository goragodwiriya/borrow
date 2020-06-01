<?php
/**
 * @filesource modules/inventory/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Inventory\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Init Module.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล.
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        // สามารถบริหารจัดการ inventory ได้
        if (Login::checkPermission($login, 'can_manage_inventory')) {
            // เมนูตั้งค่า
            $submenus = array(
                array(
                    'text' => '{LNG_Settings}',
                    'url' => 'index.php?module=inventory-settings',
                ),
                array(
                    'text' => '{LNG_Inventory}',
                    'url' => 'index.php?module=inventory-setup',
                ),
                array(
                    'text' => '{LNG_Add New} {LNG_Equipment}',
                    'url' => 'index.php?module=inventory-write',
                ),
            );
            foreach (Language::get('INVENTORY_CATEGORIES', array()) as $type => $text) {
                $submenus[] = array(
                    'text' => $text,
                    'url' => 'index.php?module=inventory-categories&amp;type='.$type,
                );
            }
            $submenus[] = array(
                'text' => '{LNG_Units}',
                'url' => 'index.php?module=inventory-categories&amp;type=unit',
            );
            $menu->add('settings', '{LNG_Inventory}', null, $submenus);
        }
    }
}
