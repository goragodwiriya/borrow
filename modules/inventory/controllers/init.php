<?php
/**
 * @filesource modules/inventory/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Init;

use Gcms\Api as ApiController;

/**
 * ลงทะเบียนเมนูและสิทธิ์ของโมดูล
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * สิทธิ์ของโมดูล
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        $permissions[] = [
            'value' => 'can_manage_inventory',
            'text' => '{LNG_Can manage the} {LNG_Inventory}'
        ];

        return $permissions;
    }

    /**
     * เมนูของโมดูล
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login) {
            return $menus;
        }

        $children = [];

        if (ApiController::hasPermission($login, 'can_manage_inventory')) {
            $children[] = [
                'title' => '{LNG_List of} {LNG_Inventory}',
                'url' => '/inventory-setup',
                'icon' => 'icon-list'
            ];
            foreach (\Inventory\Category\Controller::items() as $key => $label) {
                $children[] = [
                    'title' => $label,
                    'url' => '/inventory-categories?type='.$key,
                    'icon' => 'icon-tags'
                ];
            }
        }

        if (ApiController::hasPermission($login, 'can_config')) {
            $children[] = [
                'title' => '{LNG_Module Settings}',
                'url' => '/inventory-settings',
                'icon' => 'icon-cog'
            ];
        }

        if (empty($children)) {
            return $menus;
        }

        $inventoryMenu = [
            [
                'title' => '{LNG_Inventory}',
                'icon' => 'icon-product',
                'children' => $children
            ]
        ];

        // ถ้าไม่มีเมนูตั้งค่า (ไม่มีสิทธิ์ can_config) ให้แสดงเป็นเมนูหลักแทน
        if (!isset($menus['settings'])) {
            $menus['inventory'] = $inventoryMenu[0];

            return $menus;
        }

        return parent::insertMenuChildren($menus, $inventoryMenu, 'settings', null, 1);
    }
}
