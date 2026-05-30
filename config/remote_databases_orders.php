<?php

/**
 * 远程数据库订单同步配置文件
 *
 * 配置说明：
 * - remote_databases: 远程数据库连接配置列表
 * - tables_to_sync: 需要同步的订单相关表
 * - primary_keys: 各表主键配置
 * - sync_order: 表同步顺序（按外键依赖关系）
 * - foreign_keys: 外键关联配置
 * - non_auto_increment_tables: 非自增主键表
 * - mappings: ID映射和进度文件配置
 * - logging: 日志配置
 * - concurrency: 并发配置
 * - ip_retry: IP重试配置
 *
 * @author CodeArts Agent
 * @version 1.0
 * @date 2026-04-14
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 远程数据库配置列表
    |--------------------------------------------------------------------------
    |
    | 配置需要同步的远程数据库连接信息
    | 每个数据库包含：name、website、db_host、db_port、db_name、db_username、db_pwd、server_ip、server_inner_ip
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | 需要同步的订单表列表
    |--------------------------------------------------------------------------
    |
    | 定义需要同步的订单相关表
    | 按照OpenCart订单表结构
    |
    */
    'tables_to_sync' => [
        'oc_order',                      // 主订单表
        'oc_order_history',              // 订单历史
        'oc_order_product',              // 订单产品
        'oc_order_option',               // 订单选项
        'oc_order_total',                // 订单总计
        'oc_order_voucher',              // 订单代金券
        'oc_order_shipment',             // 订单发货
        'oc_order_recurring',            // 订单周期
        'oc_order_recurring_transaction', // 订单周期交易
        'oc_order_status',               // 订单状态
    ],

    /*
    |--------------------------------------------------------------------------
    | 各表主键配置
    |--------------------------------------------------------------------------
    |
    | 定义每个表的主键字段
    | 单主键使用字符串，复合主键使用数组
    |
    */
    'primary_keys' => [
        'oc_order' => 'order_id',
        'oc_order_history' => 'order_history_id',
        'oc_order_product' => 'order_product_id',
        'oc_order_option' => 'order_option_id',
        'oc_order_total' => 'order_total_id',
        'oc_order_voucher' => 'order_voucher_id',
        'oc_order_shipment' => 'order_shipment_id',
        'oc_order_recurring' => 'order_recurring_id',
        'oc_order_recurring_transaction' => 'order_recurring_transaction_id',
        'oc_order_status' => ['order_status_id', 'language_id'], // 复合主键
    ],

    /*
    |--------------------------------------------------------------------------
    | 表同步顺序
    |--------------------------------------------------------------------------
    |
    | 定义表同步的顺序，必须按照外键依赖关系排序
    | 主表在前，子表在后
    |
    */
    'sync_order' => [
        'oc_order',                      // 主订单表（必须最先同步）
        'oc_order_status',               // 订单状态（复合主键，无外键依赖）
        'oc_order_history',              // 订单历史（依赖 oc_order, oc_order_status）
        'oc_order_product',              // 订单产品（依赖 oc_order）
        'oc_order_option',               // 订单选项（依赖 oc_order, oc_order_product）
        'oc_order_total',                // 订单总计（依赖 oc_order）
        'oc_order_voucher',              // 订单代金券（依赖 oc_order）
        'oc_order_shipment',             // 订单发货（依赖 oc_order）
        'oc_order_recurring',            // 订单周期（依赖 oc_order）
        'oc_order_recurring_transaction', // 订单周期交易（依赖 oc_order_recurring）
    ],

    /*
    |--------------------------------------------------------------------------
    | 外键关联配置
    |--------------------------------------------------------------------------
    |
    | 定义子表外键与主表的关联关系
    | 格式：子表 => [外键字段 => 主表]
    |
    | 注意：oc_order.customer_id 关联到本地 oc_customer 表
    |       由于客户数据可能已通过其他方式同步，需要通过 email 查找本地客户
    |
    */
    'foreign_keys' => [
        'oc_order' => [
            'customer_id' => 'oc_customer',  // 关联到本地客户表（通过email查找）
        ],
        'oc_order_history' => [
            'order_id' => 'oc_order',
            'order_status_id' => 'oc_order_status',
        ],
        'oc_order_product' => [
            'order_id' => 'oc_order',
        ],
        'oc_order_option' => [
            'order_id' => 'oc_order',
            'order_product_id' => 'oc_order_product',
        ],
        'oc_order_total' => [
            'order_id' => 'oc_order',
        ],
        'oc_order_voucher' => [
            'order_id' => 'oc_order',
        ],
        'oc_order_shipment' => [
            'order_id' => 'oc_order',
        ],
        'oc_order_recurring' => [
            'order_id' => 'oc_order',
        ],
        'oc_order_recurring_transaction' => [
            'order_recurring_id' => 'oc_order_recurring',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 非自增主键表
    |--------------------------------------------------------------------------
    |
    | 定义主键不是自增ID的表
    | 这些表在同步时不会移除主键，直接使用原主键值
    |
    */
    'non_auto_increment_tables' => [
        'oc_order_status', // 复合主键表
    ],

    /*
    |--------------------------------------------------------------------------
    | ID映射和进度文件配置
    |--------------------------------------------------------------------------
    |
    | 配置映射文件、进度文件、历史备份的存储路径
    |
    */
    'mappings' => [
        // JSON映射文件（运行时ID映射）
        'json_file' => storage_path('app/remote_order_sync/id_mappings.json'),

        // CSV进度文件（增量同步进度）
        'progress_file' => storage_path('app/remote_order_sync/progress.csv'),

        // 历史备份目录
        'history_dir' => storage_path('app/remote_order_sync/history'),

        // 历史备份保留天数
        'history_retention_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    |
    | 配置日志文件存储路径
    |
    */
    'logging' => [
        'log_dir' => storage_path('logs/remote_order_sync'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 并发配置
    |--------------------------------------------------------------------------
    |
    | 配置并发同步的参数
    |
    */
    'concurrency' => [
        'max_concurrent_dbs' => 13,      // 最大并发数据库数
    ],

    /*
    |--------------------------------------------------------------------------
    | IP重试配置
    |--------------------------------------------------------------------------
    |
    | 配置IP连接重试次数
    |
    */
    'ip_retry' => [
        'max_retries' => 3,              // 每个IP最大重试次数
    ],

    /*
    |--------------------------------------------------------------------------
    | 超时配置
    |--------------------------------------------------------------------------
    |
    | 配置连接和查询超时时间
    |
    */
    'connection_timeout' => 3,           // 连接超时（秒）
    'query_timeout' => 300,              // 查询超时（秒）
];
