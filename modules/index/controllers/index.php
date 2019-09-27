<?php
/**
 * @filesource modules/index/controllers/index.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Index;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Language;
use Kotchasan\Template;

/**
 * Controller สำหรับแสดงหน้าเว็บ.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * หน้าหลักเว็บไซต์ (index.html)
     * ให้ผลลัพท์เป็น HTML.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        // ตัวแปรป้องกันการเรียกหน้าเพจโดยตรง
        define('MAIN_INIT', 'indexhtml');
        // session cookie
        $request->initSession();
        // ตรวจสอบการ login
        Login::create();
        // กำหนด skin ให้กับ template
        Template::init(self::$cfg->skin);
        // View
        self::$view = new \Gcms\View();
        // Javascript
        self::$view->addScript('var WEB_URL="'.WEB_URL.'";');
        if ($login = Login::isMember()) {
            // โหลดเมนู
            self::$menus = \Index\Menu\Controller::init($login);
            // Javascript
            self::$view->addScript('var FIRST_MODULE="'.self::$menus->home().'";');
            // โหลดค่าติดตั้งโมดูล
            $dir = ROOT_PATH.'modules/';
            $f = @opendir($dir);
            if ($f) {
                while (false !== ($text = readdir($f))) {
                    if ($text != '.' && $text != '..' && $text != 'index' && $text != 'css' && $text != 'js' && is_dir($dir.$text)) {
                        if (is_file($dir.$text.'/controllers/init.php')) {
                            require_once $dir.$text.'/controllers/init.php';
                            $className = '\\'.ucfirst($text).'\Init\Controller';
                            if (method_exists($className, 'execute')) {
                                $className::execute($request, self::$menus, $login);
                            }
                        }
                    }
                }
                closedir($f);
            }
            // Controller หลัก
            $page = createClass('Index\Main\Controller')->execute($request);
            $bodyclass = 'mainpage';
        } else {
            // forgot, login, register
            $page = createClass('Index\Welcome\Controller')->execute($request);
            $bodyclass = 'loginpage';
        }
        $languages = '';
        foreach (Language::installedLanguage() as $item) {
            $t = '{LNG_Language} '.strtoupper($item);
            $languages .= '<li><a id=lang_'.$item.' href="'.$page->canonical()->withParams(array('lang' => $item), true).'" aria-label="'.$t.'"  style="background-image:url('.WEB_URL.'language/'.$item.'.gif)" tabindex=1>&nbsp;</a></li>';
        }
        if ($bodyclass == 'loginpage' && is_file(ROOT_PATH.DATA_FOLDER.'images/bg_image.png')) {
            $bg_image = WEB_URL.DATA_FOLDER.'images/bg_image.png';
        } else {
            $bg_image = '';
        }
        if (is_file(ROOT_PATH.DATA_FOLDER.'images/logo.png')) {
            $logo = '<img src="'.WEB_URL.DATA_FOLDER.'images/logo.png" alt="{WEBTITLE}">&nbsp;{WEBTITLE}';
        } else {
            $logo = '<span class="'.self::$cfg->default_icon.'">{WEBTITLE}</span>';
        }
        // เนื้อหา
        self::$view->setContents(array(
            // main template
            '/{MAIN}/' => $page->detail(),
            // โลโก
            '/{LOGO}/' => $logo,
            // language menu
            '/{LANGUAGES}/' => $languages,
            // title
            '/{TITLE}/' => $page->title(),
            // class สำหรับ body
            '/{BODYCLASS}/' => $bodyclass,
            // รูปภาพพื้นหลัง
            '/{BGIMAGE}/' => $bg_image,
            // เวอร์ชั่น
            '/{VERSION}/' => self::$cfg->version,
        ));
        if ($login) {
            self::$view->setContents(array(
                // เมนู
                '/{MENUS}/' => self::$menus->render($page->menu(), $login),
                // แสดงชื่อคน Login
                '/{LOGINNAME}/' => empty($login['name']) ? $login['username'] : $login['name'],
            ));
        }
        // ส่งออก เป็น HTML
        $response = new Response();
        if ($page->status() == 404) {
            $response = $response->withStatus(404)->withAddedHeader('Status', '404 Not Found');
        }
        $response->withContent(self::$view->renderHTML())->send();
    }
}
