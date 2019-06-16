<?php
/**
 * @filesource modules/index/views/welcome.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Welcome;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Template;

/**
 * Login, Forgot, Register.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Kotchasan\View
{
    /**
     * ฟอร์มเข้าระบบ.
     *
     * @param Request $request
     *
     * @return object
     */
    public static function login(Request $request)
    {
        $login_action = $request->request('ret')->url();
        // template
        $template = Template::create('', '', 'login');
        $template->add(array(
            '/<FACEBOOK>(.*)<\/FACEBOOK>/s' => empty(self::$cfg->facebook_appId) ? '' : '\\1',
            '/<GOOGLE>(.*)<\/GOOGLE>/s' => empty(self::$cfg->google_client_id) ? '' : '\\1',
            '/{TOKEN}/' => $request->createToken(),
            '/{EMAIL}/' => Login::$login_params['username'],
            '/{PASSWORD}/' => isset(Login::$login_params['password']) ? Login::$login_params['password'] : '',
            '/{MESSAGE}/' => Login::$login_message,
            '/{CLASS}/' => empty(Login::$login_message) ? 'hidden' : (empty(Login::$login_input) ? 'message' : 'error'),
            '/{URL}/' => $request->getUri()->withoutParams('action'),
            '/{LOGINMENU}/' => self::menus('login'),
            '/{LOGIN_ACTION}/' => $login_action == '' ? WEB_URL.'index.php' : $login_action,
        ));

        return (object) array(
            'detail' => $template->render(),
            'title' => self::$cfg->web_title.' - '.Language::get('Login with an existing account'),
        );
    }

    /**
     * ฟอร์มขอรหัสผ่านใหม่.
     *
     * @param Request $request
     *
     * @return object
     */
    public static function forgot(Request $request)
    {
        // template
        $template = Template::create('', '', 'forgot');
        $template->add(array(
            '/{TOKEN}/' => $request->createToken(),
            '/{EMAIL}/' => Login::$login_params['username'],
            '/{MESSAGE}/' => Login::$login_message,
            '/{CLASS}/' => empty(Login::$login_message) ? 'hidden' : (empty(Login::$login_input) ? 'message' : 'error'),
            '/{LOGINMENU}/' => self::menus('forgot'),
        ));

        return (object) array(
            'detail' => $template->render(),
            'title' => self::$cfg->web_title.' - '.Language::get('Get new password'),
        );
    }

    /**
     * ฟอร์มสมัครสมาชิก
     *
     * @param Request $request
     *
     * @return object
     */
    public static function register(Request $request)
    {
        // template
        $template = Template::create('', '', 'register');
        $template->add(array(
            '/{Terms of Use}/' => '<a href="{WEBURL}index.php?module=terms">{LNG_Terms of Use}</a>',
            '/{Privacy Policy}/' => '<a href="{WEBURL}index.php?module=policy">{LNG_Privacy Policy}</a>',
            '/{TOKEN}/' => $request->createToken(),
            '/{LOGINMENU}/' => self::menus('register'),
        ));

        return (object) array(
            'detail' => $template->render(),
            'title' => self::$cfg->web_title.' - '.Language::get('Register'),
        );
    }

    /**
     * เมนูหน้าเข้าระบบ.
     *
     * @param  $from
     *
     * @return string
     */
    public static function menus($from)
    {
        $menus = array();
        if (in_array($from, array('register', 'forgot'))) {
            $menus[] = '<a href="index.php?action=login">{LNG_Sign in}</a>';
        }
        if (in_array($from, array('forgot', 'login')) && !empty(self::$cfg->user_register)) {
            $menus[] = '<a href="index.php?action=register">{LNG_Register}</a>';
        }
        if (in_array($from, array('register', 'login')) && !empty(self::$cfg->user_forgot)) {
            $menus[] = '<a href="index.php?action=forgot">{LNG_Forgot}</a>';
        }

        return empty($menus) ? '' : implode('&nbsp;/&nbsp;', $menus);
    }
}
