<?php
/**
 * @filesource modules/borrow/controllers/home.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Home;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=borrow-home.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟังก์ชั่นสร้าง card.
     *
     * @param Request         $request
     * @param \Kotchasan\Html $card
     * @param array           $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        $items = \Borrow\Home\Model::get($login);
        \Index\Home\Controller::renderCard($card, 'icon-exchange', '{LNG_Borrow} &amp; {LNG_Return} ('.$login['name'].')', number_format($items->pending), Language::find('BORROW_STATUS', null, 0), 'index.php?module=borrow-setup&amp;status=0');
        \Index\Home\Controller::renderCard($card, 'icon-valid', '{LNG_Borrow} &amp; {LNG_Return} ('.$login['name'].')', number_format($items->confirmed), Language::find('BORROW_STATUS', null, 2), 'index.php?module=borrow-setup&amp;status=2');
        \Index\Home\Controller::renderCard($card, 'icon-warning', '{LNG_Borrow} &amp; {LNG_Return} ('.$login['name'].')', number_format($items->returned), '{LNG_Un-Returned items}', 'index.php?module=borrow-setup&amp;status=2&amp;due=1');
        if (isset($items->allpending)) {
            \Index\Home\Controller::renderCard($card, 'icon-exchange', '{LNG_Borrow} &amp; {LNG_Return} ({LNG_Can be approve})', number_format($items->allpending), '{LNG_Waiting list}', 'index.php?module=borrow-report&amp;status=0');
        }
    }
}
