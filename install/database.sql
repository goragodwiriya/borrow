-- phpMyAdmin SQL Dump
-- version 4.6.6
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 16, 2019 at 01:54 AM
-- Server version: 10.1.38-MariaDB
-- PHP Version: 7.0.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
--
-- Table structure for table `{prefix}_language`
--

CREATE TABLE `{prefix}_language` (
  `id` int(11) NOT NULL,
  `key` text COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `owner` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `js` tinyint(1) NOT NULL,
  `th` text COLLATE utf8_unicode_ci,
  `en` text COLLATE utf8_unicode_ci
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------
--
-- Table structure for table `{prefix}_category`
--

CREATE TABLE `{prefix}_category` (
  `id` int(11) NOT NULL,
  `type` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `category_id` int(11) DEFAULT 0,
  `topic` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `color` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `published` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `{prefix}_category`
--

INSERT INTO `{prefix}_category` (`id`, `type`, `category_id`, `topic`, `color`, `published`) VALUES
(1, 'model_id', 2, 'Asus', '', 1),
(2, 'type_id', 3, 'โปรเจ็คเตอร์', '', 1),
(3, 'type_id', 2, 'เครื่องพิมพ์', '', 1),
(4, 'model_id', 3, 'Cannon', '', 1),
(5, 'category_id', 1, 'เครื่องใช้ไฟฟ้า', '', 1),
(6, 'category_id', 2, 'วัสดุสำนักงาน', '', 1),
(7, 'model_id', 1, 'Apple', '', 1),
(8, 'type_id', 1, 'เครื่องคอมพิวเตอร์', '', 1),
(9, 'model_id', 4, 'ACER', '', 1),
(10, 'type_id', 4, 'จอมอนิเตอร์', '', 1),
(11, 'unit', 1, 'ชิ้น', NULL, 1),
(12, 'unit', 2, 'อัน', NULL, 1),
(13, 'unit', 3, 'กล่อง', NULL, 1),
(14, 'unit', 4, 'เครื่อง', NULL, 1),
(15, 'unit', 5, 'แพ็ค', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_borrow`
--

CREATE TABLE `{prefix}_borrow` (
  `id` int(11) NOT NULL,
  `borrow_no` varchar(20) COLLATE utf8_unicode_ci NOT NULL COMMENT 'เลขที่ใบเบิก',
  `transaction_date` date NOT NULL COMMENT 'วันเวลาที่ทำรายการ',
  `borrower_id` int(11) NOT NULL COMMENT 'ผู้เบิก',
  `borrow_date` date NOT NULL COMMENT 'วันที่ต้องการเบิก',
  `return_date` date DEFAULT NULL COMMENT 'กำหนดคืน'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_borrow_items`
--

CREATE TABLE `{prefix}_borrow_items` (
  `id` int(11) NOT NULL,
  `borrow_id` int(11) NOT NULL,
  `topic` varchar(90) COLLATE utf8_unicode_ci NOT NULL,
  `num_requests` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_inventory`
--

CREATE TABLE `{prefix}_inventory` (
  `id` int(11) NOT NULL,
  `equipment` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `serial` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `type_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `unit` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `detail` text COLLATE utf8_unicode_ci,
  `category_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_number`
--

CREATE TABLE `{prefix}_number` (
  `id` int(11) NOT NULL,
  `inventory_no` int(11) DEFAULT '0',
  `borrow_no` int(11) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `{prefix}_number`
--

INSERT INTO `{prefix}_number` (`id`, `inventory_no`, `borrow_no`) VALUES
(1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_user`
--

CREATE TABLE `{prefix}_user` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `salt` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `token` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT 0,
  `permission` text COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `sex` varchar(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `id_card` varchar(13) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address` varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `provinceID` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `province` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `zipcode` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `visited` int(11) DEFAULT 0,
  `lastvisited` int(11) DEFAULT 0,
  `session_id` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `create_date` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `social` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for table `{prefix}_category`
--
ALTER TABLE `{prefix}_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `{prefix}_language`
--
ALTER TABLE `{prefix}_language`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `{prefix}_user`
--
ALTER TABLE `{prefix}_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `{prefix}_borrow`
--
ALTER TABLE `{prefix}_borrow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `{prefix}_borrow_items`
--
ALTER TABLE `{prefix}_borrow_items`
  ADD PRIMARY KEY (`borrow_id`,`id`) USING BTREE;

--
-- Indexes for table `{prefix}_inventory`
--
ALTER TABLE `{prefix}_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_no` (`serial`);


--
-- Indexes for table `{prefix}_number`
--
ALTER TABLE `{prefix}_number`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for table `{prefix}_category`
--
ALTER TABLE `{prefix}_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_language`
--
ALTER TABLE `{prefix}_language`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_user`
--
ALTER TABLE `{prefix}_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_borrow`
--
ALTER TABLE `{prefix}_borrow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_inventory`
--
ALTER TABLE `{prefix}_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
