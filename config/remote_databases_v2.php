<?php

return [
    // 13个远程数据库配置
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

    // 映射文件与进度记录配置（JSON+CSV）
    'mappings' => [
        // JSON运行时映射文件路径
        'json_file' => storage_path('app/mappings/id_mapping.json'),

        // CSV历史备份目录
        'history_dir' => storage_path('app/mappings/history'),

        // CSV同步进度记录文件路径
        'progress_file' => storage_path('app/mappings/sync_last_max_ids.csv'),

        // 历史备份保留天数
        'history_retention_days' => 7,
    ],

    // 数据库连接超时配置（秒）
    'connection_timeout' => 3,  // IP测试超时时间
    'query_timeout' => 300,     // 单个数据库查询超时时间

    // IP连接重试配置
    'ip_retry' => [
        'max_retries' => 3,  // 每个IP最多重试次数
    ],

    // 本地数据库配置（硬编码）
    'local_database' => [
        'host' => 'localhost',
        'port' => '3306',
        'database' => 'saveb_cp2026',
        'username' => 'saveb_cp2026',
        'password' => 'Z35kctFFdFMYGPzs',
        'prefix' => '',
    ],

    // 要同步的14张表
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
    ],

    // 表依赖顺序（必须按此顺序同步）
    'sync_order' => [
        'oc_customer_group',                              // 基础表
        'oc_customer_group_description',                 // 关联oc_customer_group
        'oc_customer',                                    // 用户主表
        'oc_address',                                     // 关联oc_customer
        'oc_customer_activity',                           // 关联oc_customer
        'oc_customer_affiliate',                         // 关联oc_customer
        'oc_customer_approval',                          // 关联oc_customer
        'oc_customer_history',                           // 关联oc_customer
        'oc_customer_ip',                                // 关联oc_customer
        'oc_customer_login',                             // 关联oc_customer（通过email）
        'oc_customer_online',                            // 关联oc_customer
        'oc_customer_reward',                            // 关联oc_customer
        'oc_customer_search',                            // 关联oc_customer
        'oc_customer_transaction',                       // 关联oc_customer
    ],

    // 外键关联配置（子表外键 => 主表）
    'foreign_keys' => [
        'oc_address' => ['customer_id' => 'oc_customer'],
        'oc_customer_activity' => ['customer_id' => 'oc_customer'],
        'oc_customer_affiliate' => ['customer_id' => 'oc_customer'],
        'oc_customer_approval' => ['customer_id' => 'oc_customer'],
        'oc_customer_history' => ['customer_id' => 'oc_customer'],
        'oc_customer_ip' => ['customer_id' => 'oc_customer'],
        'oc_customer_login' => ['email' => 'oc_customer'],  // 特殊关联：通过email
        'oc_customer_online' => ['customer_id' => 'oc_customer'],
        'oc_customer_reward' => ['customer_id' => 'oc_customer'],
        'oc_customer_transaction' => ['customer_id' => 'oc_customer'],
    ],

    // 非自增主键表配置（这些表的主键不是自增ID，不应移除）
    'non_auto_increment_tables' => [
        'oc_customer_online',  // 主键是 ip 字段（IP地址字符串）
        'oc_customer_affiliate',  // 主键是 customer_id（一对一关系，同时是外键）
    ],

    // 特殊关联配置（email关联等）
    'special_relations' => [
        'oc_customer_login' => [
            'type' => 'email',
            'field' => 'email',
            'target_table' => 'oc_customer',
        ],
    ],

    // 并发配置
    'concurrency' => [
        'max_concurrent_dbs' => 13,  // 最大并发数据库数
        'max_concurrent_tables' => 3, // 每个数据库最大并发表数
    ],

    // 日志配置
    'logging' => [
        'log_dir' => storage_path('logs/remote_sync'),
        'log_retention_days' => 7,
    ],
];
