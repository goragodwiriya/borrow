<?php

if (defined('ROOT_PATH')) {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        include ROOT_PATH.'install/upgrade1.php';
    } else {
        $error = false;
        // ค่าติดตั้งฐานข้อมูล
        $db_config = include ROOT_PATH.'settings/database.php';
        $db_config = $db_config['mysql'];
        try {
            $options = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            );
            $dbdriver = empty($db_config['dbdriver']) ? 'mysql' : $db_config['dbdriver'];
            $hostname = empty($db_config['hostname']) ? 'localhost' : $db_config['hostname'];
            $conn = new \PDO($dbdriver.':host='.$hostname.';dbname='.$db_config['dbname'], $db_config['username'], $db_config['password'], $options);
            $conn->query("SET SESSION sql_mode = ''");
        } catch (\PDOException $e) {
            $error = true;
            echo '<h2>ความผิดพลาดในการเชื่อมต่อกับฐานข้อมูล</h2>';
            echo '<p class=warning>ไม่สามารถเชื่อมต่อกับฐานข้อมูลของคุณได้ในขณะนี้</p>';
            echo '<p>อาจเป็นไปได้ว่า</p>';
            echo '<ol>';
            echo '<li>เซิร์ฟเวอร์ของฐานข้อมูลของคุณไม่สามารถใช้งานได้ในขณะนี้</li>';
            echo '<li>ค่ากำหนดของฐานข้อมูลไม่ถูกต้อง (ตรวจสอบไฟล์ settings/database.php)</li>';
            echo '<li>ไม่พบฐานข้อมูลที่ต้องการติดตั้ง กรุณาสร้างฐานข้อมูลก่อน หรือใช้ฐานข้อมูลที่มีอยู่แล้ว</li>';
            echo '<li class="incorrect">'.$e->getMessage().'</li>';
            echo '</ol>';
            echo '<p>หากคุณไม่สามารถดำเนินการแก้ไขข้อผิดพลาดด้วยตัวของคุณเองได้ ให้ติดต่อผู้ดูแลระบบเพื่อขอข้อมูลที่ถูกต้อง หรือ ลองติดตั้งใหม่</p>';
            echo '<p><a href="index.php?step=1" class="button large pink">กลับไปลองใหม่</a></p>';
        }
        if (!$error) {
            // เชื่อมต่อฐานข้อมูลสำเร็จ
            $content = array('<li class="correct">เชื่อมต่อฐานข้อมูลสำเร็จ</li>');
            try {
                // ตาราง user
                $table = $db_config['prefix'].'_user';
                if (empty($config['password_key'])) {
                    // อัปเดทข้อมูลผู้ดูแลระบบ
                    $config['password_key'] = uniqid();
                }
                // ตรวจสอบการ login
                updateAdmin($conn, $table, $_POST['username'], $_POST['password'], $config['password_key']);
                if (!fieldExists($conn, $table, 'social')) {
                    $conn->query("ALTER TABLE `$table` CHANGE `fb` `social` TINYINT(1) NOT NULL DEFAULT '0'");
                }
                if (!fieldExists($conn, $table, 'country')) {
                    $conn->query("ALTER TABLE `$table` ADD `country` VARCHAR(2)");
                }
                if (!fieldExists($conn, $table, 'province')) {
                    $conn->query("ALTER TABLE `$table` ADD `province` VARCHAR(50)");
                }
                if (!fieldExists($conn, $table, 'token')) {
                    $conn->query("ALTER TABLE `$table` ADD `token` VARCHAR(50) NULL AFTER `password`");
                }
                $conn->query("ALTER TABLE `$table` CHANGE `address` `address` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL");
                $conn->query("ALTER TABLE `$table` CHANGE `password` `password` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");
                $content[] = '<li class="correct">ปรับปรุงตาราง `'.$table.'` สำเร็จ</li>';
                // บันทึก settings/config.php
                $config['version'] = $new_config['version'];
                if (isset($new_config['default_icon'])) {
                    $config['default_icon'] = $new_config['default_icon'];
                }
                $f = save($config, ROOT_PATH.'settings/config.php');
                $content[] = '<li class="'.($f ? 'correct' : 'incorrect').'">บันทึก <b>config.php</b> ...</li>';
            } catch (\PDOException $e) {
                $content[] = '<li class="incorrect">'.$e->getMessage().'</li>';
            } catch (\Exception $e) {
                $content[] = '<li class="incorrect">'.$e->getMessage().'</li>';
                $error = true;
            }
            if (!$error) {
                echo '<h2>ปรับรุ่นเรียบร้อย</h2>';
                echo '<p>การปรับรุ่นได้ดำเนินการเสร็จเรียบร้อยแล้ว หากคุณต้องการความช่วยเหลือในการใช้งาน คุณสามารถ ติดต่อสอบถามได้ที่ <a href="https://www.kotchasan.com" target="_blank">https://www.kotchasan.com</a></p>';
                echo '<ul>'.implode('', $content).'</ul>';
                echo '<p class=warning>กรุณาลบไดเร็คทอรี่ <em>install/</em> ออกจาก Server ของคุณ</p>';
                echo '<p>คุณควรปรับ chmod ให้ไดเร็คทอรี่ <em>datas/</em> และ <em>settings/</em> (และไดเร็คทอรี่อื่นๆที่คุณได้ปรับ chmod ไว้ก่อนการปรับรุ่น) ให้เป็น 644 ก่อนดำเนินการต่อ (ถ้าคุณได้ทำการปรับ chmod ไว้ด้วยตัวเอง)</p>';
                echo '<p><a href="../index.php" class="button large admin">เข้าระบบ</a></p>';
            } else {
                echo '<h2>ปรับรุ่นไม่สำเร็จ</h2>';
                echo '<p>การปรับรุ่นยังไม่สมบูรณ์ ลองตรวจสอบข้อผิดพลาดที่เกิดขึ้นและแก้ไขดู หากคุณต้องการความช่วยเหลือการติดตั้ง คุณสามารถ ติดต่อสอบถามได้ที่ <a href="https://www.kotchasan.com" target="_blank">https://www.kotchasan.com</a></p>';
                echo '<ul>'.implode('', $content).'</ul>';
                echo '<p><a href="." class="button large admin">ลองใหม่</a></p>';
            }
        }
    }
}

function updateAdmin($conn, $table_name, $username, $password, $password_key)
{
    $query = $conn->prepare("SELECT `id`,`username`,`password`,`salt` FROM `$table_name` WHERE `username`=:username AND `status`=1 LIMIT 1");
    $query->bindValue(':username', $username);
    $query->execute();
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    if ($result === false) {
        throw new \Exception('ชื่อผู้ใช้ ไม่ถูกต้อง');
    } elseif ($result['password'] === sha1($password.$result['salt'])) {
        // password เวอร์ชั่นเก่า
        $query = $conn->prepare("UPDATE `$table_name` SET `password`=:password WHERE `id`=:id");
        $query->bindValue(':id', $result['id']);
        $query->bindValue(':password', sha1($password_key.$password.$result['salt']));
        $query->execute();
    } elseif ($result['password'] != sha1($password_key.$password.$result['salt'])) {
        throw new \Exception('รหัสผ่าน ไม่ถูกต้อง');
    }
}

/**
 * ตรวจสอบฟิลด์.
 *
 * @param resource $conn
 * @param string   $table_name
 * @param string   $field
 *
 * @return bool
 */
function fieldExists($conn, $table_name, $field)
{
    $query = $conn->query("SHOW COLUMNS FROM `$table_name` LIKE '$field'");
    $result = $query->fetchAll(\PDO::FETCH_ASSOC);

    return empty($result) ? false : true;
}

/**
 * ตรวจสอบ index ซ้ำ.
 *
 * @param $conn
 * @param $table_name
 * @param $index
 */
function indexExists($conn, $table_name, $index)
{
    $query = $conn->query("SELECT index_name FROM INFORMATION_SCHEMA.STATISTICS WHERE table_name='$table_name' AND index_name='$index'");
    $result = $query->fetchAll(\PDO::FETCH_ASSOC);

    return empty($result) ? false : true;
}

/**
 * @param $config
 * @param $file
 */
function save($config, $file)
{
    $f = @fopen($file, 'wb');
    if ($f !== false) {
        if (!preg_match('/^.*\/([^\/]+)\.php?/', $file, $match)) {
            $match[1] = 'config';
        }
        fwrite($f, '<'."?php\n/* $match[1].php */\nreturn ".var_export((array) $config, true).';');
        fclose($f);

        return true;
    } else {
        return false;
    }
}
