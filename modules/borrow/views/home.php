<?php
/**
 * @filesource modules/borrow/views/home.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Borrow\Home;

use Gcms\Login;
use Kotchasan\Html;

/**
 * หน้า Home.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * หน้า Home.
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $section = Html::create('section');
        $section->add('h3', array(
            'innerHTML' => '<span class="icon-shipping">{LNG_Booking calendar}</span>',
        ));
        $div = $section->add('div', array(
            'class' => 'setup_frm',
        ));
        $div->add('div', array(
            'id' => 'borrow-calendar',
            'class' => 'margin-left-right-bottom-top',
        ));
        /* Javascript */
        $div->script('initBorrowCalendar();');
        // คืนค่า HTML

        return $section->render();
    }
}
