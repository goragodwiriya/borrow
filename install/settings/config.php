<?php
/* config.php */
return array(
    'version' => '6.0.5',
    'web_title' => 'E-Borrow',
    'web_description' => 'ระบบ ยืม-คืน พัสดุ ออนไลน์',
    'timezone' => 'Asia/Bangkok',
    'member_status' => array(
        0 => 'สมาชิก',
        1 => 'ผู้ดูแลระบบ',
        2 => 'ช่างซ่อม',
        3 => 'ผู้รับผิดชอบ'
    ),
    'color_status' => array(
        0 => '#259B24',
        1 => '#FF0000',
        2 => '#0E0EDA',
        3 => '#827717'
    ),
    'default_icon' => 'icon-exchange',
    'inventory_w' => 600,
    'borrow_no' => '%04d',
    'borrow_prefix' => 'B%Y%M-'
);
