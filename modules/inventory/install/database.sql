-- ---------------------------------------------------------------------------
-- modules/inventory/install/database.sql — ตารางของโมดูล inventory กลาง
--
-- ตัวติดตั้ง (install/cli-fresh.php, install/step4.php) และตัวปรับรุ่น
-- (install/upgrade_core.php → ensureTable) อ่านไฟล์นี้เอง ผ่าน schemaFiles()
-- ซึ่งกวาด modules/*/install/database.sql ให้ครบทุกโมดูลที่ติดตั้งอยู่
-- ถอดโฟลเดอร์โมดูลออก = ไม่มีตารางเหล่านี้ · วางกลับ = ได้ครบเหมือนเดิม
--
-- การพาฐานเดิมของแต่ละผลิตภัณฑ์มาถึงหน้าตานี้อยู่ใน install/upgrade.php ข้าง ๆ
--
-- กติกาที่ยึดตลอดทั้งไฟล์
--   1. ชื่อกลางเลือกจาก "คำที่แกนของเฟรมเวิร์กใช้อยู่แล้ว" ก่อนเสมอ
--      (category.is_active, user.provinceID) แล้วจึงดูว่าใครใช้เยอะกว่ากัน
--   2. คอลัมน์เดิมของทุกระบบ "คงไว้" ไม่ลบ ไม่เปลี่ยนชื่อ — เพิ่มคอลัมน์กลาง
--      แล้วคัดลอกค่า โมดูลเขียนคู่กันไว้ระหว่างเปลี่ยนผ่าน
--   3. ตารางที่ระบบไหนไม่ใช้ ก็ยังสร้าง (ว่างเปล่า ไม่กินอะไร) เพื่อให้โมดูล
--      ชุดเดียวทำงานได้ทุกที่โดยไม่ต้องมีเงื่อนไข "ระบบนี้มีตารางนั้นไหม"
--
-- ที่มาของแต่ละคอลัมน์กำกับไว้ท้ายบรรทัด :
--   [oms] [oas] [inv] = inventory/borrow  [กลาง] = ของใหม่ที่ตกลงกันใน Phase 1.2
-- ---------------------------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ---------------------------------------------------------------------------
-- inventory — Product Master
--
-- รหัสสินค้าระดับ master ใช้ชื่อ product_code ไม่ใช่ product_no เพราะ
-- product_no ถูกใช้ไปแล้วในความหมายอื่น (เลขครุภัณฑ์รายชิ้น) ที่ตาราง
-- inventory_items ของ inventory/borrow — ใช้ชื่อเดียวกันสองความหมายในโมดูล
-- ชุดเดียวคือการวางกับดักไว้ให้ตัวเอง
--
-- is_active คือชื่อกลาง ไม่ใช่ inuse : ตารางแกน category ใช้ is_active อยู่แล้ว
-- (upgrade_core แปลง published → is_active ให้ทุกโปรเจ็ค) และ inventory/borrow
-- ซึ่งเป็นสองผลิตภัณฑ์ที่มีผู้ใช้จริงมากที่สุดก็ใช้ is_active — เหลือ oas/oms
-- ที่ใช้ inuse ซึ่งทั้งคู่แก้ได้อิสระกว่า
--
-- category_id เป็น varchar(10) ตามตารางแกน category ไม่ใช่ int แบบ oms
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_inventory` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(150) DEFAULT NULL,                 -- [กลาง] oms:product_no · oas:product_code · inv:ไม่มี
  `product_no` varchar(150) DEFAULT NULL,                   -- [oms] คงไว้ เขียนคู่กับ product_code
  `topic` varchar(150) NOT NULL,                            -- ทุกระบบ
  `description` text DEFAULT NULL,                          -- [oms varchar(255)] ขยายเป็น text ตาม oas
  `category_id` varchar(10) DEFAULT NULL,                   -- ตามตารางแกน category
  `model_id` varchar(10) DEFAULT NULL,                      -- [inv]
  `type_id` varchar(10) DEFAULT NULL,                       -- [inv]
  `unit` varchar(20) DEFAULT NULL,                          -- [oms]
  `price` double NOT NULL DEFAULT 0,                        -- [oms] ราคาขายตั้งต้น
  `vat` double NOT NULL DEFAULT 0,                          -- [oms]
  `cost` double NOT NULL DEFAULT 0,                         -- [oms][oas] ต้นทุนตั้งต้น (ต้นทุนจริงมาจาก cost layer)
  `count_stock` tinyint(1) NOT NULL DEFAULT 1,              -- 0=ไม่นับ 1=นับรวม 2=นับแยกรายชิ้น
  `stockable` tinyint(1) NOT NULL DEFAULT 1,                -- [oas] = (count_stock > 0) เขียนคู่กัน
  `allow_negative` tinyint(1) NOT NULL DEFAULT 0,           -- [oas] ยอมให้ยอดติดลบไหม
  `stock` double NOT NULL DEFAULT 0,                        -- [oms] cache ของยอดรวม (ความจริงอยู่ที่ ledger)
  `is_active` tinyint(1) NOT NULL DEFAULT 1,                -- [กลาง] ชื่อเดียวกับตารางแกน
  `inuse` tinyint(1) DEFAULT NULL,                          -- [oms][oas] คงไว้ เขียนคู่กับ is_active
  `last_update` int(11) unsigned NOT NULL DEFAULT 0,        -- [oms]
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `product_no` (`product_no`),
  KEY `category_id` (`category_id`),
  KEY `model_id` (`model_id`),
  KEY `type_id` (`type_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- inventory_items — หน่วยย่อยของสินค้า (SKU / เลขครุภัณฑ์ / ซีเรียล)
--
-- ⚠️ inventory/borrow/oms ใช้ product_no เป็น PRIMARY KEY อยู่ ตัวปรับรุ่นต้อง
-- เพิ่ม id แล้วย้าย PK มาที่ id ในคำสั่งเดียว (ไม่ได้ลบข้อมูล แค่ย้ายคีย์) :
--   ALTER TABLE t ADD COLUMN `id` int(11) NOT NULL FIRST;
--   SET @i = 0; UPDATE t SET `id` = (@i := @i + 1) ORDER BY `product_no`;
--   ALTER TABLE t DROP PRIMARY KEY, ADD PRIMARY KEY (`id`),
--                 MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,
--                 ADD UNIQUE KEY `product_no` (`product_no`);
-- ต้องย้าย เพราะ ledger/cost layer อ้างถึงหน่วยย่อยด้วย inventory_item_id
-- และเพราะฐานที่ติดตั้งใหม่กับฐานที่ปรับรุ่นมาต้องได้สคีมาเท่ากันเป๊ะ
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(150) NOT NULL,                              -- [กลาง] oas:sku · oms/inv:product_no
  `product_no` varchar(150) DEFAULT NULL,                   -- [oms][inv] คงไว้ เดิมเป็น PK
  `barcode` varchar(150) DEFAULT NULL,                      -- [oas]
  `inventory_id` int(11) NOT NULL,
  `topic` varchar(150) DEFAULT NULL,                        -- [oms]
  `unit` varchar(50) DEFAULT NULL,
  `price` double DEFAULT NULL,
  `cut_stock` double NOT NULL DEFAULT 1,                    -- จำนวนที่ตัดจากสต๊อกหลักต่อ 1 หน่วยของรายการนี้
  `stock` double NOT NULL DEFAULT 0,                        -- [inv][oas] cache (ความจริงอยู่ที่ ledger)
  `instock` tinyint(1) NOT NULL DEFAULT 0,                  -- [oms]
  `url` varchar(255) DEFAULT NULL,                          -- [oms]
  `last_update` int(11) NOT NULL DEFAULT 0,                 -- [oms]
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `product_no` (`product_no`),
  KEY `barcode` (`barcode`),
  KEY `inventory_id` (`inventory_id`),
  KEY `instock` (`instock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- inventory_meta — ข้อมูลเพิ่มเติมของสินค้าแบบ key/value
-- value เป็น mediumtext ตาม oas (ของ oms/inv เป็น text — ขยายได้ ไม่เสียข้อมูล)
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_inventory_meta` (
  `inventory_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` mediumtext NOT NULL,
  KEY `idx_inventory_meta` (`inventory_id`,`name`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- inventory_stock — ยอดคงเหลือปัจจุบัน (cache ที่คำนวณจาก ledger)
--
-- ⚠️ ของ oas ไม่มีดัชนี unique เลย ยอดของ SKU เดียวจึงมีได้หลายแถวโดยไม่มีอะไร
-- ฟ้อง ตรงนี้บังคับ UNIQUE (inventory_id, inventory_item_id) และให้สินค้าที่นับ
-- รวม (ไม่มีหน่วยย่อย) ใช้ inventory_item_id = 0 ไม่ใช่ NULL เพราะ NULL ซ้ำกัน
-- ได้ในดัชนี unique ของ MySQL ซึ่งทำให้ข้อบังคับนี้ไม่มีผลกับสินค้ากลุ่มนั้นเลย
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_inventory_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL DEFAULT 0,           -- 0 = ยอดระดับสินค้า (count_stock = 1)
  `sku` varchar(150) NOT NULL DEFAULT '',
  `qty` double NOT NULL DEFAULT 0,
  `reserved_qty` double NOT NULL DEFAULT 0,                 -- [oas] จองไว้แล้วแต่ยังไม่ตัด
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_balance` (`inventory_id`,`inventory_item_id`),
  KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- inventory_stock_movement — สมุดบัญชีเดินสต๊อก (ledger)
--
-- นี่คือ "ความจริง" ของยอดคงเหลือ ทุกการเปลี่ยนยอดต้องผ่านตารางนี้เท่านั้น
-- ผ่าน Posting API จุดเดียว ห้ามโมดูลไหนไปแก้ inventory_stock.qty ตรง ๆ
--
-- movement_direction เป็นเครื่องหมายที่เชื่อถือได้ (in/out) ส่วน movement_type
-- บอกว่าเป็นการเคลื่อนไหวประเภทไหน — ดูรายการที่อนุญาตในหัวข้อ 4.6 ของแผน
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_inventory_stock_movement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL DEFAULT 0,
  `sku` varchar(150) NOT NULL DEFAULT '',
  `movement_direction` enum('in','out') NOT NULL DEFAULT 'in',
  `movement_type` varchar(20) NOT NULL,                     -- ทะเบียนกลาง (4.6)
  `reference_type` varchar(20) DEFAULT NULL,                -- order | borrow | repair | adjustment | opening
  `reference_id` int(11) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `reference_item_id` int(11) DEFAULT NULL,
  `source_movement_id` int(11) DEFAULT NULL,                -- การเคลื่อนไหวที่กลับรายการของแถวนี้
  `quantity` double NOT NULL DEFAULT 0,                     -- เป็นบวกเสมอ ทิศทางอยู่ที่ movement_direction
  `unit_cost` double DEFAULT NULL,
  `total_cost` double DEFAULT NULL,
  `note` text DEFAULT NULL,
  `occurred_at` datetime NOT NULL,                          -- เวลาที่เกิดรายการจริง (ใช้เรียง FIFO)
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item_time` (`inventory_id`,`inventory_item_id`,`occurred_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `movement_type` (`movement_type`),
  KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- inventory_cost_layer / inventory_cost_allocation — ต้นทุนแบบ FIFO
--
-- ⚠️ ทั้งสองตารางนี้ยังไม่เคยเดินจริงที่ไหนเลย (oas = 0 แถวจริง, oms ไม่มีตาราง)
-- จึงเป็นของใหม่ที่ต้องมีชุดทดสอบของตัวเอง ไม่ใช่การย้ายของที่พิสูจน์แล้ว
-- โครงตารางยกจาก oas เพราะรองรับต้นทุนต่อ lot และการคืนของเข้า layer เดิมได้
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_inventory_cost_layer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL DEFAULT 0,
  `sku` varchar(150) NOT NULL DEFAULT '',
  `movement_id` int(11) DEFAULT NULL,                       -- [กลาง] แถวใน ledger ที่ทำให้เกิด layer นี้
  `reference_type` varchar(20) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `reference_item_id` int(11) DEFAULT NULL,
  `source_allocation_id` int(11) DEFAULT NULL,              -- layer ที่เกิดจากการคืนของ
  `received_qty` double NOT NULL DEFAULT 0,
  `remaining_qty` double NOT NULL DEFAULT 0,
  `unit_cost` double NOT NULL DEFAULT 0,
  `currency` varchar(3) NOT NULL DEFAULT 'THB',
  `note` text DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fifo` (`inventory_id`,`inventory_item_id`,`received_at`),
  KEY `idx_remaining` (`remaining_qty`),
  KEY `movement_id` (`movement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}_inventory_cost_allocation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `layer_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL DEFAULT 0,
  `sku` varchar(150) NOT NULL DEFAULT '',
  `movement_id` int(11) DEFAULT NULL,
  `source_allocation_id` int(11) DEFAULT NULL,
  `reference_type` varchar(20) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `reference_item_id` int(11) DEFAULT NULL,
  `quantity` double NOT NULL DEFAULT 0,
  `unit_cost` double NOT NULL DEFAULT 0,
  `total_cost` double NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `layer_id` (`layer_id`),
  KEY `movement_id` (`movement_id`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- inventory_template — แม่แบบเอกสาร (ยกมาจาก oms)
--
-- ชนิดเอกสารเป็น "ข้อมูล" ไม่ใช่ "โค้ด" — แต่ละผลิตภัณฑ์จึงมีชุดของตัวเองได้
-- โดยไม่ต้องแก้โมดูล ตรงนี้เพิ่ม document_type เป็นชื่อกลาง (เดิม oms ใช้ status)
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- customer — ลูกค้า/คู่ค้า (คนละตารางกับ user ซึ่งเป็นผู้ใช้ระบบ)
--
-- ฐานเป็นของ oms (มีข้อมูลจริง 83 ราย) แล้วเติมคอลัมน์ของ oas ที่ oms ไม่มี
-- ชื่อที่ชนกันเลือกตามแกน : provinceID (เหมือน user) ไม่ใช่ province_id ของ oas
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- orders — หัวเอกสาร
--
-- ชื่อตารางใช้ orders (พหูพจน์) ตาม oms ไม่ใช่ `order` ของ oas ด้วยสองเหตุผล
--   1. order เป็นคำสงวนของ SQL ต้องใส่ backquote ทุกครั้งที่เอ่ยถึงตลอดไป
--   2. ข้อมูลจริงชุดเดียวที่มีคือของ oms (246 ใบ ต่อเนื่อง 8 ปี) การเปลี่ยนชื่อ
--      ฝั่ง oas กระทบแค่ข้อมูลทดสอบ 16 ใบในระบบที่ยังไม่เผยแพร่
--
-- ชื่อคอลัมน์ยึดของ oms เป็นหลัก (ข้อมูลจริงอยู่ที่นั่น) แล้วเติมของ oas เฉพาะ
-- ที่ oms ไม่มีจริง ๆ — snapshot ลูกค้า, สายเอกสาร, สถานะเอกสาร/การชำระเงิน
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- order_items — บรรทัดรายการของเอกสาร
--
-- ⚠️ ตารางนี้ "ไม่ใช่" การเดินสต๊อก — บรรทัดในใบเสนอราคาไม่ได้ตัดสต๊อกอะไรเลย
-- ของเดิมของ oms รวมสองเรื่องไว้ในตาราง stock ตารางเดียวแล้วใช้ธง cut_stock
-- แยกเอา ซึ่งทำให้ "ยอดคงเหลือ" กับ "รายการในเอกสาร" แก้กันไปมาไม่ได้
--
-- ตาราง stock เดิมของ oms คงไว้ทั้ง 323 แถว ไม่ลบ — Phase 4 คัดลอกเป็น
-- order_items เพื่อให้เอกสารเก่าพิมพ์ได้เหมือนเดิม แล้วเริ่มเดิน ledger จากศูนย์
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- ข้อมูลอ้างอิงที่ระบบต้องมี — แม่แบบเอกสารตั้งต้น
--
-- ถ้าไม่มีแถวเหล่านี้ โมดูลสร้างเอกสารไม่ได้เลยสักใบ และเมนูจะว่างเปล่า
-- รหัสและพฤติกรรมตรงกับทะเบียนกลาง (หัวข้อ 4.6 ของแผน)
-- แต่ละไซต์แก้/เพิ่ม/ปิดได้เองจากหน้าแม่แบบเอกสาร โดยไม่ต้องแก้โค้ด
--
-- in_stock = 1 รับของเข้าสต๊อก · cut_stock = 1 ตัดของออก · ว่างทั้งคู่ = ไม่แตะสต๊อก
-- ---------------------------------------------------------------------------
