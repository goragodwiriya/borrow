<?php
/**
 * @filesource Kotchasan/Antispam.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Kotchasan;

/**
 * คลาสสำหรับจัดการ Antispam.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Antispam extends \Kotchasan\KBase
{
    /**
     * current Antispam ID.
     *
     * @var string
     */
    protected $antispamchar;

    /**
     * create class
     * ถ้ามีการกำหนดค่ามาจะใช้ในการตวจสอบ Antispam.
     *
     * @param string $id  รหัส Antispam ถ้าเป็น null หมายถึงการสร้าง Antispam ใหม่,
     * @param int    $len ความยาวของอักขระ Antispam (ค่าเริ่มต้น 4)
     */
    public function __construct($id = null, $len = 4)
    {
        if ($id) {
            $this->antispamchar = (string) $id;
        } else {
            $this->antispamchar = Text::rndname(32);
            $_SESSION[$this->antispamchar] = Text::rndname($len);
        }
    }

    /**
     * สร้าง Image สำหรับ Antispam
     * คืนค่าเป็น tag IMG, $toHTML เป็น false คืนค่าข้อความว่าง.
     *
     * @param string $id     คีย์ของ Antispam
     * @param bool   $toHTML true คืนค่าเป็น tag IMG, false สร้างรูปภาพ
     *
     * @return string
     */
    public static function createImage($id, $toHTML = false)
    {
        if (!empty($_SESSION[$id]) && preg_match('/[a-z0-9]{32,32}/', $id)) {
            $antispamchar = $_SESSION[$id];
            $im = imagecreate(80, 20);
            // transparent
            $trans_colour = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans_colour);
            // random points
            for ($i = 0; $i <= 128; ++$i) {
                $point_color = imagecolorallocate($im, rand(0, 255), rand(0, 255), rand(0, 255));
                imagesetpixel($im, rand(2, 128), rand(2, 38), $point_color);
            }
            // output characters
            for ($i = 0; $i < strlen($antispamchar); ++$i) {
                $text_color = imagecolorallocate($im, rand(0, 255), rand(0, 128), rand(0, 255));
                $x = 5 + $i * 20;
                $y = rand(1, 4);
                imagechar($im, 5, $x, $y, $antispamchar[$i], $text_color);
            }
            // png image
            if ($toHTML) {
                if (ob_get_length() > 0) {
                    ob_end_flush();
                }
                ob_start();
                imagepng($im);
                $image_string = ob_get_contents();
                ob_end_clean();
            } else {
                header('Content-type: image/png');
                imagepng($im);
            }
            // clear
            imagedestroy($im);
            // return

            return $toHTML ? '<span><img src="data:image/png;base64,'.base64_encode($image_string).'" alt=Antispam></span>' : '';
        }
    }

    /**
     * ลบ Antispan.
     */
    public function delete()
    {
        unset($_SESSION[$this->antispamchar]);
    }

    /**
     * อ่านค่าค่าคีย์ของ Antispam
     * คืนค่าคีย์ของ Antispam.
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->antispamchar;
    }

    /**
     * คืนค่า Antispam.
     *
     * @return string|null
     */
    public function getValue()
    {
        return empty($_SESSION[$this->antispamchar]) ? null : $_SESSION[$this->antispamchar];
    }

    /**
     * ฟังก์ชั่นตรวจสอบ Antispam กับ $value
     * คืนค่า true ถ้าตรงกัน, false ไม่พบ Antispam หรือไม่ถูกต้อง.
     *
     * @param string $value
     *
     * @return bool
     */
    public function valid($value)
    {
        return !empty($this->antispamchar) && !empty($_SESSION[$this->antispamchar]) && $_SESSION[$this->antispamchar] === $value;
    }
}
