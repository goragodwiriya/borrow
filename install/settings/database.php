<?php

/* settings/database.php */

return array(
    'mysql' => array(
        'dbdriver' => 'mysql',
        'username' => 'root',
        'password' => '',
        'dbname' => 'borrow',
        'prefix' => 'brw',
    ),
    'tables' => array(
        'category' => 'category',
        'language' => 'language',
        'number' => 'number',
        'borrow' => 'borrow',
        'borrow_items' => 'borrow_items',
        'repair' => 'repair',
        'inventory' => 'inventory',
        'user' => 'user',
    ),
);
