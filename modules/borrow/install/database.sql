-- ---------------------------------------------------------------------------
-- modules/borrow/install/database.sql — ตารางที่โมดูล borrow เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- ประกาศสองที่ = ติดตั้งใหม่ล้มด้วย "Table already exists" และนิยามสองชุด
-- จะค่อย ๆ ต่างกันจนไซต์ที่อัปเกรดคนละเส้นทางได้สคีมาไม่เหมือนกัน
--
-- ทั้งการติดตั้งใหม่ (common.php::schemaFiles) และการปรับรุ่น (ensureTable)
-- อ่านนิยามจากไฟล์นี้ไฟล์เดียว
-- ---------------------------------------------------------------------------

CREATE TABLE `{prefix}_borrow` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `borrow_no` varchar(20) NOT NULL,
  `transaction_date` date NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `borrow_no` (`borrow_no`),
  KEY `idx_borrower` (`borrower_id`,`borrow_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}_borrow_items` (
  `id` int(11) NOT NULL,
  `borrow_id` int(11) NOT NULL,
  `topic` varchar(90) NOT NULL,
  `num_requests` int(11) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT NULL,
  `product_no` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`borrow_id`,`id`),
  KEY `idx_status` (`status`),
  KEY `product_no` (`product_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
