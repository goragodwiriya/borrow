<?php
/**
 * @filesource modules/borrow/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Borrow\Init;

use Gcms\Api as ApiController;
use Kotchasan\Language;

/**
 * ลงทะเบียนเมนูและสิทธิ์ของโมดูลยืม-คืน
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
            'value' => 'can_approve_borrow',
            'text' => '{LNG_Can be approve} ({LNG_Borrow} & {LNG_Return})'
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
        foreach (Language::get('BORROW_STATUS', []) as $value => $text) {
            $children[] = [
                'title' => $text,
                'url' => '/myborrow?status='.$value,
                'icon' => 'icon-list'
            ];
        }
        $children[] = [
            'title' => '{LNG_Un-Returned items}',
            'url' => '/myborrow?status=2&due=1',
            'icon' => 'icon-warning'
        ];
        $children[] = [
            'title' => '{LNG_Add Borrow}',
            'url' => '/borrow',
            'icon' => 'icon-plus'
        ];

        if (ApiController::hasPermission($login, 'can_approve_borrow')) {
            // รายงานของเจ้าหน้าที่ (เห็นรายการของทุกคน)
            $children[] = [
                'title' => '{LNG_Borrow Report}',
                'url' => '/borrow-report',
                'icon' => 'icon-report'
            ];
        }

        $menus = parent::insertMenuAfter($menus, [
            [
                'title' => '{LNG_Equipment}',
                'url' => '/borrow-inventory',
                'icon' => 'icon-barcode'
            ],
            [
                'title' => '{LNG_Borrow} & {LNG_Return}',
                'icon' => 'icon-exchange',
                'children' => $children
            ]
        ], 'dashboard');

        if (ApiController::hasPermission($login, 'can_config')) {
            $menus = parent::insertMenuChildren($menus, [
                [
                    'title' => '{LNG_Borrow} & {LNG_Return}',
                    'url' => '/borrow-settings',
                    'icon' => 'icon-exchange'
                ]
            ], 'settings', null, 1);
        }

        return $menus;
    }
}
