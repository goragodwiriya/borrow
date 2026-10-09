<?php
/**
 * modules/inventory/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูลกลาง
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เองสำหรับทุกโมดูลที่มี ตัวแปรที่ใช้ได้
 * คือชุดเดียวกับที่ upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ต้นทางที่ต้องรองรับ (ดูหัวข้อ 4.3 ของ UPGRADE_PLAN_INVENTORY_BORROW_OAS.md)
 *   oms       inventory(product_no,inuse,stock) · inventory_items(PK product_no) · orders/stock · customer
 *   oas       inventory(product_code,inuse,stockable) · inventory_items(sku) · inventory_stock/_movement/cost_* · order/order_item
 *   inventory ~ borrow   inventory(is_active) · inventory_items(PK product_no) · ไม่มีเอกสาร
 *   ฐานเปล่า  ไม่มีอะไรเลย — ensureTable สร้างจาก database.sql ข้าง ๆ ให้ครบ
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 * และห้าม DROP / RENAME ข้อมูลธุรกิจ — เปลี่ยนชื่อคอลัมน์ = เพิ่มคอลัมน์ใหม่
 * แล้วคัดลอกค่า โดยคงคอลัมน์เดิมไว้
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$_inv_tables = [
    'inventory', 'inventory_items', 'inventory_meta', 'inventory_stock',
    'inventory_stock_movement', 'inventory_cost_layer', 'inventory_cost_allocation'
];
foreach ($_inv_tables as $_t) {
    if (ensureTable($db, $prefix, $prefix.'_'.$_t)) {
        $content[] = '<li class="correct">inventory: สร้างตาราง '.$_t.'</li>';
    }
}

$_t_inventory = $prefix.'_inventory';
$_t_items = $prefix.'_inventory_items';

// =============================================================================
// inventory — Product Master
// =============================================================================
if (convertToInnoDB($db, $_t_inventory)) {
    $content[] = '<li class="correct">inventory: แปลงเป็น InnoDB</li>';
}
if (convertToUtf8mb4($db, $_t_inventory)) {
    $content[] = '<li class="correct">inventory: แปลงเป็น utf8mb4</li>';
}
// category_id ต้องเป็น varchar(10) ให้ตรงกับตาราง category ของแกน
// ของ oms เป็น int(11) — แปลงเป็นข้อความไม่ทำให้ค่าหาย
if (ensureColumn($db, $_t_inventory, 'category_id', 'varchar(10)', true, null, '', 'description')) {
    $content[] = '<li class="correct">inventory: ปรับคอลัมน์ category_id</li>';
}
foreach ([
    'product_code' => ['varchar(150)', true, null, 'id'],
    'product_no' => ['varchar(150)', true, null, 'product_code'],
    'topic' => ['varchar(150)', false, null, 'product_no'],
    'description' => ['text', true, null, 'topic'],
    'model_id' => ['varchar(10)', true, null, 'category_id'],
    'type_id' => ['varchar(10)', true, null, 'model_id'],
    'unit' => ['varchar(20)', true, null, 'type_id'],
    'price' => ['double', false, 0, 'unit'],
    'vat' => ['double', false, 0, 'price'],
    'cost' => ['double', false, 0, 'vat'],
    'count_stock' => ['tinyint(1)', false, 1, 'cost'],
    'stockable' => ['tinyint(1)', false, 1, 'count_stock'],
    'allow_negative' => ['tinyint(1)', false, 0, 'stockable'],
    'stock' => ['double', false, 0, 'allow_negative'],
    'is_active' => ['tinyint(1)', false, 1, 'stock'],
    'inuse' => ['tinyint(1)', true, null, 'is_active'],
    'last_update' => ['int(11) unsigned', false, 0, 'inuse'],
    'created_at' => ['datetime', true, null, 'last_update'],
    'updated_at' => ['datetime', true, null, 'created_at']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_inventory, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">inventory: ปรับคอลัมน์ '.$_col.'</li>';
    }
}
// ค่าที่ต้องคัดลอกข้ามชื่อคอลัมน์ — ทำเฉพาะแถวที่ยังว่าง จึงรันซ้ำได้
$db->query("UPDATE `$_t_inventory` SET `product_code` = `product_no` WHERE (`product_code` IS NULL OR `product_code` = '') AND `product_no` IS NOT NULL AND `product_no` != ''");
$db->query("UPDATE `$_t_inventory` SET `product_no` = `product_code` WHERE (`product_no` IS NULL OR `product_no` = '') AND `product_code` IS NOT NULL AND `product_code` != ''");
// is_active เป็นชื่อกลาง (ตรงกับตาราง category ของแกน) inuse คงไว้และเขียนคู่กัน
$db->query("UPDATE `$_t_inventory` SET `is_active` = `inuse` WHERE `inuse` IS NOT NULL AND `is_active` != `inuse`");
$db->query("UPDATE `$_t_inventory` SET `inuse` = `is_active` WHERE `inuse` IS NULL");
// stockable เป็นมุมมองหนึ่งของ count_stock ไม่ใช่สวิตช์อิสระ
$db->query("UPDATE `$_t_inventory` SET `stockable` = IF(`count_stock` > 0, 1, 0) WHERE `stockable` != IF(`count_stock` > 0, 1, 0)");
if (ensureIndexes($db, $_t_inventory, [
    'product_no' => '`product_no`',
    'category_id' => '`category_id`',
    'model_id' => '`model_id`',
    'type_id' => '`type_id`',
    'is_active' => '`is_active`'
])) {
    $content[] = '<li class="correct">inventory: ปรับดัชนี</li>';
}
// product_code ต้องไม่ซ้ำ — ปฏิเสธอย่างสุภาพถ้ายังซ้ำอยู่ ดีกว่าล้มกลางทาง
if (!$db->indexExists($_t_inventory, 'product_code')) {
    $db->query("UPDATE `$_t_inventory` SET `product_code` = NULL WHERE `product_code` = ''");
    $_dup = $db->customQuery(
        "SELECT `product_code` AS `v`, COUNT(*) AS `c` FROM `$_t_inventory`
         WHERE `product_code` IS NOT NULL GROUP BY `product_code` HAVING `c` > 1 LIMIT 10"
    );
    if (!empty($_dup)) {
        $_list = [];
        foreach ($_dup as $_row) {
            $_list[] = htmlspecialchars((string) $_row->v, ENT_QUOTES).' ('.(int) $_row->c.' รายการ)';
        }
        throw new \Exception(
            'ไม่สามารถบังคับให้รหัสสินค้าไม่ซ้ำกันได้ เพราะตอนนี้ยังมีรหัสซ้ำอยู่<br>'
            .implode(', ', $_list).'<br>'
            .'กรุณาแก้รหัสสินค้าในตาราง <code>'.$_t_inventory.'</code> ให้ไม่ซ้ำกัน แล้วปรับรุ่นใหม่อีกครั้ง'
        );
    }
    $db->query("ALTER TABLE `$_t_inventory` ADD UNIQUE `product_code` (`product_code`)");
    $content[] = '<li class="correct">inventory: เพิ่ม UNIQUE product_code</li>';
}

// =============================================================================
// inventory_items — หน่วยย่อย
//
// ⚠️ ของเดิมสามผลิตภัณฑ์ใช้ product_no เป็น PRIMARY KEY ต้องย้าย PK มาที่ id
// เพราะ ledger กับ cost layer อ้างหน่วยย่อยด้วย inventory_item_id (ตัวเลข)
// และเพราะฐานที่ติดตั้งใหม่กับฐานที่ปรับรุ่นมาต้องได้สคีมาเท่ากันเป๊ะ
// การย้ายคีย์ไม่ได้ลบข้อมูลสักแถว — product_no ยังอยู่ครบและยังห้ามซ้ำเหมือนเดิม
// =============================================================================
// ⚠️ สคีมากลางใช้ `inventory`.`id` เป็น int unsigned ส่วนของเดิมบางโปรเจ็ค
// (เช่น inventory/borrow) เป็น int ธรรมดา — ต่างกันแค่ signed/unsigned แต่
// cli-verify ถือว่าสคีมาไม่ตรงกับการติดตั้งใหม่ และคีย์นอกที่ชี้มาจะชนิดไม่ตรง
// MODIFY ไม่แตะค่าเดิมและไม่รีเซ็ต AUTO_INCREMENT
if (!$db->isColumnType($_t_inventory, 'id', 'int(11) unsigned')) {
    $db->query("ALTER TABLE `$_t_inventory` MODIFY `id` int(11) unsigned NOT NULL AUTO_INCREMENT");
    $content[] = '<li class="correct">inventory: ปรับชนิดคอลัมน์ id เป็น int unsigned</li>';
}

if (convertToInnoDB($db, $_t_items)) {
    $content[] = '<li class="correct">inventory_items: แปลงเป็น InnoDB</li>';
}
if (convertToUtf8mb4($db, $_t_items)) {
    $content[] = '<li class="correct">inventory_items: แปลงเป็น utf8mb4</li>';
}
if (!$db->fieldExists($_t_items, 'id')) {
    $db->query("ALTER TABLE `$_t_items` ADD COLUMN `id` int(11) NOT NULL FIRST");
    $db->query("SET @row_number = 0");
    $db->query("UPDATE `$_t_items` SET `id` = (@row_number := @row_number + 1) ORDER BY `product_no`");
    $db->query(
        "ALTER TABLE `$_t_items` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`),
         MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD UNIQUE KEY `product_no` (`product_no`)"
    );
    $content[] = '<li class="correct">inventory_items: ย้าย PRIMARY KEY จาก product_no ไป id</li>';
}
foreach ([
    'sku' => ['varchar(150)', false, null, 'id'],
    'product_no' => ['varchar(150)', true, null, 'sku'],
    'barcode' => ['varchar(150)', true, null, 'product_no'],
    'inventory_id' => ['int(11)', false, null, 'barcode'],
    'topic' => ['varchar(150)', true, null, 'inventory_id'],
    'unit' => ['varchar(50)', true, null, 'topic'],
    'price' => ['double', true, null, 'unit'],
    'cut_stock' => ['double', false, 1, 'price'],
    'stock' => ['double', false, 0, 'cut_stock'],
    'instock' => ['tinyint(1)', false, 0, 'stock'],
    'url' => ['varchar(255)', true, null, 'instock'],
    'last_update' => ['int(11)', false, 0, 'url']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_items, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">inventory_items: ปรับคอลัมน์ '.$_col.'</li>';
    }
}
$db->query("UPDATE `$_t_items` SET `sku` = `product_no` WHERE (`sku` IS NULL OR `sku` = '') AND `product_no` IS NOT NULL AND `product_no` != ''");
$db->query("UPDATE `$_t_items` SET `product_no` = `sku` WHERE (`product_no` IS NULL OR `product_no` = '') AND `sku` != ''");
if (ensureIndexes($db, $_t_items, [
    'barcode' => '`barcode`',
    'inventory_id' => '`inventory_id`',
    'instock' => '`instock`'
])) {
    $content[] = '<li class="correct">inventory_items: ปรับดัชนี</li>';
}
foreach (['sku', 'product_no'] as $_idx) {
    if ($db->indexExists($_t_items, $_idx) && indexIsUnique($db, $_t_items, $_idx)) {
        continue;
    }
    if ($db->indexExists($_t_items, $_idx)) {
        $db->query("ALTER TABLE `$_t_items` DROP INDEX `$_idx`");
    }
    $_dup = $db->customQuery(
        "SELECT `$_idx` AS `v`, COUNT(*) AS `c` FROM `$_t_items`
         WHERE `$_idx` IS NOT NULL AND `$_idx` != '' GROUP BY `$_idx` HAVING `c` > 1 LIMIT 10"
    );
    if (!empty($_dup)) {
        $_list = [];
        foreach ($_dup as $_row) {
            $_list[] = htmlspecialchars((string) $_row->v, ENT_QUOTES).' ('.(int) $_row->c.' รายการ)';
        }
        throw new \Exception(
            'ไม่สามารถบังคับให้ <code>'.$_idx.'</code> ของหน่วยย่อยไม่ซ้ำกันได้<br>'.implode(', ', $_list).'<br>'
            .'กรุณาแก้ข้อมูลในตาราง <code>'.$_t_items.'</code> ให้ไม่ซ้ำกัน แล้วปรับรุ่นใหม่อีกครั้ง'
        );
    }
    $db->query("ALTER TABLE `$_t_items` ADD UNIQUE `$_idx` (`$_idx`)");
    $content[] = '<li class="correct">inventory_items: เพิ่ม UNIQUE '.$_idx.'</li>';
}

// =============================================================================
// inventory_meta · inventory_stock · ledger · cost layer
// ตารางกลุ่มนี้ไม่มีในผลิตภัณฑ์ไหนนอกจาก oas — ensureTable สร้างให้แล้วข้างบน
// ที่ต้องทำคือปรับของ oas ให้ตรงสคีมากลาง (inventory_item_id ห้ามเป็น NULL)
// =============================================================================
$_t_meta = $prefix.'_inventory_meta';
if (convertToInnoDB($db, $_t_meta)) {
    $content[] = '<li class="correct">inventory_meta: แปลงเป็น InnoDB</li>';
}
if (convertToUtf8mb4($db, $_t_meta)) {
    $content[] = '<li class="correct">inventory_meta: แปลงเป็น utf8mb4</li>';
}
if (ensureColumn($db, $_t_meta, 'value', 'mediumtext', false, null, '', 'name')) {
    $content[] = '<li class="correct">inventory_meta: ขยาย value เป็น mediumtext</li>';
}
if (ensureIndexes($db, $_t_meta, ['idx_inventory_meta' => '`inventory_id`, `name`', 'name' => '`name`'])) {
    $content[] = '<li class="correct">inventory_meta: ปรับดัชนี</li>';
}
// ระบบเดิมตั้งชื่อดัชนีเดียวกันว่า inventory_id — ซ้ำซ้อนกับ idx_inventory_meta
// ที่เพิ่งสร้าง ปล่อยไว้คือจ่ายค่าดูแลดัชนีสองชุดที่ทำงานเหมือนกันทุกการเขียน
if ($db->indexExists($_t_meta, 'inventory_id') && $db->indexExists($_t_meta, 'idx_inventory_meta')) {
    $db->query("ALTER TABLE `$_t_meta` DROP INDEX `inventory_id`");
    $content[] = '<li class="correct">inventory_meta: ลบดัชนีซ้ำซ้อน inventory_id</li>';
}
foreach (['inventory_stock', 'inventory_stock_movement', 'inventory_cost_layer', 'inventory_cost_allocation'] as $_t) {
    $_table = $prefix.'_'.$_t;
    // NULL ซ้ำกันได้ในดัชนี unique ของ MySQL ยอดของสินค้าที่นับรวมจึงต้องใช้ 0
    $db->query("UPDATE `$_table` SET `inventory_item_id` = 0 WHERE `inventory_item_id` IS NULL");
    if (ensureColumn($db, $_table, 'inventory_item_id', 'int(11)', false, 0, '', 'inventory_id')) {
        $content[] = '<li class="correct">'.$_t.': inventory_item_id ห้ามเป็น NULL</li>';
    }
    $db->query("UPDATE `$_table` SET `sku` = '' WHERE `sku` IS NULL");
}
// คอลัมน์ที่สคีมากลางเพิ่มจากของเดิม — ไซต์ที่มีตารางเหล่านี้อยู่แล้ว (oas) จะขาดไป
// ถ้าไม่เติม Posting API จะล้มตอนเขียนยอดคงเหลือ ด้วย Unknown column
foreach ([
    'inventory_stock' => ['updated_at' => ['datetime', true, null, 'reserved_qty']],
    'inventory_cost_layer' => ['movement_id' => ['int(11)', true, null, 'sku']],
    'inventory_cost_allocation' => ['movement_id' => ['int(11)', true, null, 'sku']]
] as $_t => $_columns) {
    foreach ($_columns as $_col => $_def) {
        if (ensureColumn($db, $prefix.'_'.$_t, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
            $content[] = '<li class="correct">'.$_t.': ปรับคอลัมน์ '.$_col.'</li>';
        }
    }
}
// ยอดคงเหลือต้องมีแถวเดียวต่อหน่วยย่อย — ของ oas ไม่มีข้อบังคับนี้เลย
if (!$db->indexExists($prefix.'_inventory_stock', 'idx_balance')) {
    $_dup = $db->customQuery(
        "SELECT `inventory_id`, `inventory_item_id`, COUNT(*) AS `c`
         FROM `{$prefix}_inventory_stock` GROUP BY `inventory_id`, `inventory_item_id` HAVING `c` > 1 LIMIT 10"
    );
    if (!empty($_dup)) {
        $_list = [];
        foreach ($_dup as $_row) {
            $_list[] = 'สินค้า '.(int) $_row->inventory_id.' หน่วยย่อย '.(int) $_row->inventory_item_id.' ('.(int) $_row->c.' แถว)';
        }
        throw new \Exception(
            'ตารางยอดคงเหลือมีสินค้าที่มียอดซ้ำกันหลายแถว ซึ่งทำให้ยอดคงเหลือไม่มีคำตอบเดียว<br>'
            .implode(', ', $_list).'<br>'
            .'กรุณารวมแถวที่ซ้ำกันในตาราง <code>'.$prefix.'_inventory_stock</code> ให้เหลือแถวเดียวต่อสินค้า แล้วปรับรุ่นใหม่'
        );
    }
    $db->query("ALTER TABLE `{$prefix}_inventory_stock` ADD UNIQUE `idx_balance` (`inventory_id`, `inventory_item_id`)");
    $content[] = '<li class="correct">inventory_stock: บังคับยอดคงเหลือแถวเดียวต่อหน่วยย่อย</li>';
}

// =============================================================================
// ตารางของฝั่งขายที่โปรเจ็คนี้ไม่ใช้แล้ว
//
// inventory_template (แม่แบบเอกสาร) · customer · orders · order_items เป็นของ
// ผลิตภัณฑ์ที่ซื้อขาย (oas / oms.in.th) โปรเจ็คนี้เป็นทะเบียนพัสดุ/ระบบยืม-คืน
// โค้ดที่ใช้ตารางเหล่านี้ถูกลบออกไปแล้ว จึงไม่ประกาศไว้ใน database.sql อีก
//
// ⚠️ ลบทิ้งได้เฉพาะตารางที่ว่างจริง ถ้าไซต์ไหนยังมีข้อมูลอยู่ให้เก็บเป็น _bak
// ตัวปรับรุ่นไม่มีสิทธิ์ทำลายข้อมูลของใคร แม้จะเป็นตารางที่เราเลิกใช้เองก็ตาม
// =============================================================================
foreach (['orders', 'order_items', 'customer', 'inventory_template'] as $_unused) {
    $_table = $prefix.'_'.$_unused;
    if (!$db->tableExists($_table)) {
        continue;
    }
    $_rows = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_table`");
    $_count = empty($_rows) ? 0 : (int) $_rows[0]->c;
    if ($_count === 0) {
        $db->query("DROP TABLE `$_table`");
        noteTableDropped($_table);
        $content[] = '<li class="correct">'.$_unused.': ลบตารางที่เลิกใช้แล้ว (ว่าง)</li>';
    } elseif (!$db->tableExists($_table.'_bak')) {
        $db->query("RENAME TABLE `$_table` TO `".$_table."_bak`");
        noteRowsMoved($_table, $_table.'_bak', $_count);
        $content[] = '<li class="warning">'.$_unused.': เลิกใช้แล้วแต่ยังมีข้อมูล '
            .number_format($_count).' แถว จึงเก็บไว้เป็น '.$_table.'_bak '
            .'ให้ตรวจแล้วลบเองเมื่อแน่ใจ</li>';
    }
}

// =============================================================================
// ยอดยกมา — ย้ายยอดคงเหลือเดิมเข้าสมุดบัญชีสต๊อก
//
// ไซต์ที่ใช้ระบบเดิมเก็บยอดคงเหลือไว้ที่คอลัมน์ `stock` ของ inventory /
// inventory_items ตรง ๆ พอมาใช้โมดูลกลาง "ความจริง" ย้ายไปอยู่ที่สมุดบัญชี
// ถ้าไม่ย้ายยอดเดิมเข้ามา ทุกสินค้าจะกลายเป็นยอด 0 ทันทีที่อัปเกรด
// (แผนข้อ 5.3 — เกณฑ์ผ่านคือยอดจาก ledger เท่ากับยอดเดิมทุกสินค้า)
//
// ⚠️ ทำครั้งเดียวตอนที่สมุดบัญชียังว่างทั้งเล่ม จึงรันซ้ำกี่รอบก็ไม่เกิดยอดซ้อน
// และไซต์ที่เดินสมุดบัญชีมาแล้ว (เช่น oas) จะไม่ถูกแตะเลย
//
// เขียนด้วย SQL ตรง ๆ ไม่ผ่าน Posting API เพราะตัวปรับรุ่นทำงานนอกแอป
// แต่ต้องได้ผลเหมือนกันเป๊ะ : movement + ยอดคงเหลือ + ชั้นต้นทุน ครบสามที่
// =============================================================================
$_t_movement = $prefix.'_inventory_stock_movement';
$_t_balance = $prefix.'_inventory_stock';
$_t_layer = $prefix.'_inventory_cost_layer';

$_rows = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_t_movement`");
if (!empty($_rows) && (int) $_rows[0]->c === 0) {
    $_now = date('Y-m-d H:i:s');
    $_note = 'ยอดยกมาตอนเริ่มใช้สมุดบัญชีสต๊อก';

    // หน่วยย่อยที่มียอดคงเหลือ — ยอดของสินค้าที่นับแยกอยู่ที่ระดับนี้
    $db->query(
        "INSERT INTO `$_t_movement`
            (`inventory_id`, `inventory_item_id`, `sku`, `movement_direction`, `movement_type`,
             `reference_type`, `quantity`, `unit_cost`, `total_cost`, `note`, `occurred_at`, `created_at`)
         SELECT I.`inventory_id`, I.`id`, IFNULL(I.`sku`, ''),
                IF(I.`stock` > 0, 'in', 'out'), IF(I.`stock` > 0, 'opening', 'adjust_out'),
                'opening', ABS(I.`stock`), IFNULL(V.`cost`, 0), ABS(I.`stock`) * IFNULL(V.`cost`, 0),
                '$_note', '$_now', '$_now'
           FROM `$_t_items` I
           LEFT JOIN `$_t_inventory` V ON V.`id` = I.`inventory_id`
          WHERE I.`stock` <> 0"
    );

    // สินค้าที่นับรวม (ยอดอยู่ที่ตัวสินค้า ไม่ได้แยกรายหน่วยย่อย)
    // ต้องไม่นับซ้ำกับยอดของหน่วยย่อยข้างบน จึงข้ามสินค้าที่หน่วยย่อยมียอดแล้ว
    $db->query(
        "INSERT INTO `$_t_movement`
            (`inventory_id`, `inventory_item_id`, `sku`, `movement_direction`, `movement_type`,
             `reference_type`, `quantity`, `unit_cost`, `total_cost`, `note`, `occurred_at`, `created_at`)
         SELECT V.`id`, 0, '',
                IF(V.`stock` > 0, 'in', 'out'), IF(V.`stock` > 0, 'opening', 'adjust_out'),
                'opening', ABS(V.`stock`), IFNULL(V.`cost`, 0), ABS(V.`stock`) * IFNULL(V.`cost`, 0),
                '$_note', '$_now', '$_now'
           FROM `$_t_inventory` V
          WHERE V.`stock` <> 0
            AND NOT EXISTS (SELECT 1 FROM `$_t_items` I WHERE I.`inventory_id` = V.`id` AND I.`stock` <> 0)"
    );

    $_rows = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_t_movement`");
    $_seeded = empty($_rows) ? 0 : (int) $_rows[0]->c;
    if ($_seeded > 0) {
        // ยอดคงเหลือ = ผลรวมของสมุดบัญชี (ขาเข้าบวก ขาออกลบ)
        $db->query(
            "INSERT INTO `$_t_balance` (`inventory_id`, `inventory_item_id`, `sku`, `qty`, `reserved_qty`, `updated_at`)
             SELECT `inventory_id`, `inventory_item_id`, MAX(`sku`),
                    SUM(IF(`movement_direction` = 'in', `quantity`, -`quantity`)), 0, '$_now'
               FROM `$_t_movement`
              GROUP BY `inventory_id`, `inventory_item_id`
             ON DUPLICATE KEY UPDATE `qty` = VALUES(`qty`), `updated_at` = VALUES(`updated_at`)"
        );

        // ชั้นต้นทุนของยอดที่รับเข้า เพื่อให้ FIFO ตัดต้นทุนของที่ขายต่อจากนี้ได้
        $db->query(
            "INSERT INTO `$_t_layer`
                (`inventory_id`, `inventory_item_id`, `sku`, `movement_id`, `reference_type`,
                 `received_qty`, `remaining_qty`, `unit_cost`, `received_at`, `created_at`)
             SELECT `inventory_id`, `inventory_item_id`, `sku`, `id`, 'opening',
                    `quantity`, `quantity`, IFNULL(`unit_cost`, 0), `occurred_at`, `created_at`
               FROM `$_t_movement`
              WHERE `movement_direction` = 'in'"
        );

        $content[] = '<li class="correct">สมุดบัญชีสต๊อก: ย้ายยอดคงเหลือเดิมเข้าเป็นยอดยกมา '.$_seeded.' รายการ</li>';
    }
}

$content[] = '<li class="correct">inventory อัปเกรดสำเร็จ</li>';
