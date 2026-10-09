<?php
/* settings/database.php */

return [
    'mysql' => [
        'dbdriver' => 'mysql',
        'username' => 'root',
        'password' => '',
        'dbname' => 'admintheme',
        'prefix' => 'app'
    ],
    'tables' => [
        'category' => 'category',
        'language' => 'language',
        'logs' => 'logs',
        'number' => 'number',
        'borrow' => 'borrow',
        'borrow_items' => 'borrow_items',
        'inventory' => 'inventory',
        'inventory_items' => 'inventory_items',
        'inventory_meta' => 'inventory_meta',
        'user' => 'user',
        'user_meta' => 'user_meta'
    ]
];
