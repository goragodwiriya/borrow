<?php
/**
 * modules/borrow/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล borrow
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เองสำหรับทุกโมดูลที่มี ตัวแปรที่ใช้ได้
 * คือชุดเดียวกับที่ upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ตารางของโมดูลต้องปรับรุ่นที่นี่ ไม่ใช่ใน install/upgrade2.php ของโปรเจ็ค
 * เพื่อให้ "นิยามตาราง + การปรับรุ่น" ของโมดูลอยู่ด้วยกันที่เดียว — โมดูลถูก
 * คัดลอกไปโปรเจ็คใหม่แล้วใช้ได้ทันทีโดยไม่ต้องตามไปแก้ตัวปรับรุ่นของโปรเจ็คนั้น
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 * และห้าม DROP / RENAME ข้อมูลธุรกิจ
 */
if (!defined('ROOT_PATH')) {
    exit;
}

// =========================================================
// borrow
// =========================================================
$table_borrow = $prefix.'_borrow';
// ⚠️ นิยามตารางอยู่ที่ modules/borrow/install/database.sql ที่เดียว
// ensureTable อ่านจากไฟล์นั้น จึงไม่มีนิยามชุดที่สองให้ค่อย ๆ ต่างกัน
if (ensureTable($db, $prefix, $table_borrow)) {
    $content[] = '<li class="correct">borrow: สร้างตารางใหม่</li>';
} else {
    if (!$db->indexExists($table_borrow, 'PRIMARY')) {
        $db->query("ALTER TABLE `$table_borrow` ADD PRIMARY KEY (`id`)");
        $content[] = '<li class="correct">borrow: เพิ่ม PRIMARY KEY</li>';
    }
    if (!isAutoIncrement($db, $table_borrow, 'id')) {
        $db->query("ALTER TABLE `$table_borrow` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        $content[] = '<li class="correct">borrow: กำหนด id เป็น AUTO_INCREMENT</li>';
    }
    // เลขที่ใบยืมต้องไม่ซ้ำ (Index\Number\Model ใช้ตรวจสอบเลขที่ซ้ำ)
    if (!$db->indexExists($table_borrow, 'borrow_no')) {
        $_dup = $db->customQuery("SELECT COUNT(*) AS `count` FROM (SELECT `borrow_no` FROM `$table_borrow` GROUP BY `borrow_no` HAVING COUNT(*) > 1) AS `Q`");
        if (empty($_dup) || (int) $_dup[0]->count === 0) {
            $db->query("ALTER TABLE `$table_borrow` ADD UNIQUE KEY `borrow_no` (`borrow_no`)");
            $content[] = '<li class="correct">borrow: เพิ่ม UNIQUE KEY borrow_no</li>';
        } else {
            $content[] = '<li class="incorrect">borrow: มีเลขที่ใบยืมซ้ำ '.$_dup[0]->count.' รายการ กรุณาแก้ไขแล้วปรับรุ่นอีกครั้ง</li>';
        }
    }
    if (!$db->indexExists($table_borrow, 'idx_borrower')) {
        $db->query("ALTER TABLE `$table_borrow` ADD INDEX `idx_borrower` (`borrower_id`, `borrow_date`)");
        $content[] = '<li class="correct">borrow: เพิ่ม index idx_borrower</li>';
    }
    if (convertToUtf8mb4($db, $table_borrow)) {
        $content[] = '<li class="correct">borrow: แปลงเป็น utf8mb4</li>';
    }
    $content[] = '<li class="correct">borrow อัปเกรดสำเร็จ</li>';
}

// =========================================================
// borrow_items
// =========================================================
$table_borrow_items = $prefix.'_borrow_items';
// ⚠️ นิยามตารางอยู่ที่ modules/borrow/install/database.sql ที่เดียว
// ensureTable อ่านจากไฟล์นั้น จึงไม่มีนิยามชุดที่สองให้ค่อย ๆ ต่างกัน
if (ensureTable($db, $prefix, $table_borrow_items)) {
    $content[] = '<li class="correct">borrow_items: สร้างตารางใหม่</li>';
} else {
    // สถานะต้องมีค่าเริ่มต้น 0 (รอตรวจสอบ) มิฉะนั้นบันทึกใบยืมใหม่ไม่ได้
    $_col = columnInfo($db, $table_borrow_items, 'status');
    if ($_col === null || (int) $_col->Default !== 0 || $_col->Default === null) {
        $db->query("ALTER TABLE `$table_borrow_items` CHANGE `status` `status` TINYINT(4) NOT NULL DEFAULT 0");
        $content[] = '<li class="correct">borrow_items: แก้ไข status เป็น TINYINT(4) DEFAULT 0</li>';
    }
    if (!$db->indexExists($table_borrow_items, 'PRIMARY')) {
        $db->query("ALTER TABLE `$table_borrow_items` ADD PRIMARY KEY (`borrow_id`, `id`)");
        $content[] = '<li class="correct">borrow_items: เพิ่ม PRIMARY KEY (borrow_id, id)</li>';
    }
    foreach (['idx_status' => 'status', 'product_no' => 'product_no'] as $_name => $_col) {
        if (!$db->indexExists($table_borrow_items, $_name)) {
            $db->query("ALTER TABLE `$table_borrow_items` ADD INDEX `$_name` (`$_col`)");
            $content[] = '<li class="correct">borrow_items: เพิ่ม index '.$_name.'</li>';
        }
    }
    if (convertToUtf8mb4($db, $table_borrow_items)) {
        $content[] = '<li class="correct">borrow_items: แปลงเป็น utf8mb4</li>';
    }
    // ชื่อพัสดุที่บันทึกไว้เป็น HTML entity จากระบบเดิม
    $_fixed = $db->query("UPDATE `$table_borrow_items` SET `topic` = REPLACE(REPLACE(REPLACE(`topic`, '&amp;', '&'), '&quot;', '\"'), '&#039;', \"'\") WHERE `topic` LIKE '%&amp;%' OR `topic` LIKE '%&quot;%' OR `topic` LIKE '%&#039;%'");
    if ($_fixed) {
        $content[] = '<li class="correct">borrow_items: แปลง HTML entity ในชื่อพัสดุ '.$_fixed.' รายการ</li>';
    }
    // ลบรายการที่ใบยืมถูกลบไปแล้ว
    $db->query("DELETE `S` FROM `$table_borrow_items` `S` LEFT JOIN `$table_borrow` `W` ON `W`.`id` = `S`.`borrow_id` WHERE `W`.`id` IS NULL");
    $content[] = '<li class="correct">borrow_items อัปเกรดสำเร็จ</li>';
}
