<?php

return [
    'remote_databases' => [
        // 示例配置，请根据实际情况修改
        // [
        //     'name' => 'shop_001',
        //     'website' => 'https://shop001.example.com',
        //     'db_host' => '192.168.1.100',
        //     'db_port' => '3306',
        //     'db_name' => 'shop_db',
        //     'db_username' => 'root',
        //     'db_pwd' => 'password',
        //     'server_ip' => '192.168.1.100',
        //     'server_inner_ip' => '192.168.1.100',
        // ],
        
    ],
    
    // Excel 文件配置
    'excel' => [
        'max_id_file' => storage_path('app/max_ids.xlsx'),
        'backup_dir' => storage_path('app/excel_backups'),
    ],
    
    // 数据库连接超时配置（秒）
    'connection_timeout' => 30,
    'query_timeout' => 300,
    
    // 本地数据库配置（硬编码）
    'local_database' => [
        'host' => 'localhost',
        'port' => '3306',
        'database' => 'saveb_cp2026', // 请根据实际情况修改
        'username' => 'saveb_cp2026', // 请根据实际情况修改
        'password' => 'Z35kctFFdFMYGPzs', // 请根据实际情况修改
        'prefix' => '',
    ],
    
    // 要同步的表
    'tables_to_sync' => [
        'oc_customer',
        'oc_address',
        'oc_customer_activity',
        'oc_customer_affiliate',
        'oc_customer_approval',
        'oc_customer_group',
        'oc_customer_group_description',
        'oc_customer_history',
        'oc_customer_ip',
        'oc_customer_login',
        'oc_customer_online',
        'oc_customer_reward',
        'oc_customer_search',
        'oc_customer_transaction',
        'oc_customer_wishlist',
    ],
    
    // 每个表的主键字段
    'primary_keys' => [
        'oc_customer' => 'customer_id',
        'oc_address' => 'address_id',
        'oc_customer_activity' => 'customer_activity_id',
        'oc_customer_affiliate' => 'customer_id',  // 使用 customer_id 作为主键（一对一关系）
        'oc_customer_approval' => 'customer_approval_id',
        'oc_customer_group' => 'customer_group_id',
        'oc_customer_group_description' => ['customer_group_id', 'language_id'],
        'oc_customer_history' => 'customer_history_id',
        'oc_customer_ip' => 'customer_ip_id',
        'oc_customer_login' => 'customer_login_id',
        'oc_customer_online' => 'ip',
        'oc_customer_reward' => 'customer_reward_id',
        'oc_customer_search' => 'customer_search_id',
        'oc_customer_transaction' => 'customer_transaction_id',
        'oc_customer_wishlist' => ['customer_id', 'product_id'],
    ],
];