<?php

/**
 * 远程数据库产品同步配置文件
 *
 * 该配置文件用于定义产品数据同步的相关参数，包括：
 * - 远程数据库连接信息
 * - 需要同步的表及其主键配置
 * - 外键关联关系
 * - 同步顺序和并发控制
 * - 日志和映射文件路径 
 *
 * @author CodeArts Agent 
 * @version 1.0
 * @date 2026-04-15
 */

return [
    // ========== 远程数据库配置 ==========
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

    // ========== 需要同步的表 ==========
    'tables_to_sync' => [
        // 分类相关表
        'oc_category',
        'oc_category_description',
        'oc_category_filter',
        'oc_category_path',
        'oc_category_to_layout',
        'oc_category_to_store',

        // 选项相关表
        'oc_option',
        'oc_option_description',
        'oc_option_value',
        'oc_option_value_description',

        // 产品相关表
        'oc_product',
        'oc_product_description',
        'oc_product_attribute',
        'oc_product_discount',
        'oc_product_filter',
        'oc_product_image',
        'oc_product_option',
        'oc_product_option_value',
        'oc_product_recurring',
        'oc_product_related',
        'oc_product_reward',
        'oc_product_special',
        'oc_product_to_category',
        'oc_product_to_download',
        'oc_product_to_layout',
        'oc_product_to_store',
    ],

    // ========== 表主键配置 ==========
    'primary_keys' => [
        // 单主键表
        'oc_category' => 'category_id',
        'oc_option' => 'option_id',
        'oc_option_value' => 'option_value_id',
        'oc_product' => 'product_id',
        'oc_product_image' => 'product_image_id',
        'oc_product_option' => 'product_option_id',
        'oc_product_option_value' => 'product_option_value_id',
        'oc_product_discount' => 'product_discount_id',
        'oc_product_reward' => 'product_reward_id',
        'oc_product_special' => 'product_special_id',

        // 复合主键表（数组形式）
        'oc_category_description' => ['category_id', 'language_id'],
        'oc_category_filter' => ['category_id', 'filter_id'],
        'oc_category_path' => ['category_id', 'path_id'],
        'oc_category_to_layout' => ['category_id', 'store_id'],
        'oc_category_to_store' => ['category_id', 'store_id'],

        'oc_option_description' => ['option_id', 'language_id'],
        'oc_option_value_description' => ['option_value_id', 'language_id'],

        'oc_product_description' => ['product_id', 'language_id'],
        'oc_product_attribute' => ['product_id', 'attribute_id', 'language_id'],
        'oc_product_filter' => ['product_id', 'filter_id'],
        'oc_product_recurring' => ['product_id', 'recurring_id', 'customer_group_id'],
        'oc_product_related' => ['product_id', 'related_id'],
        'oc_product_to_category' => ['product_id', 'category_id'],
        'oc_product_to_download' => ['product_id', 'download_id'],
        'oc_product_to_layout' => ['product_id', 'store_id'],
        'oc_product_to_store' => ['product_id', 'store_id'],
    ],

    // ========== 表同步顺序 ==========
    // 必须按照依赖关系同步：先同步主表，再同步子表
    'sync_order' => [
        // 1. 分类主表（无外键依赖）
        'oc_category',

        // 2. 分类描述表（依赖 oc_category）
        'oc_category_description',

        // 3. 分类关联表（依赖 oc_category）
        'oc_category_filter',
        'oc_category_path',
        'oc_category_to_layout',
        'oc_category_to_store',

        // 4. 选项主表（无外键依赖）
        'oc_option',

        // 5. 选项描述表（依赖 oc_option）
        'oc_option_description',

        // 6. 选项值主表（依赖 oc_option）
        'oc_option_value',

        // 7. 选项值描述表（依赖 oc_option_value, oc_option）
        'oc_option_value_description',

        // 8. 产品主表（可能依赖 oc_category, manufacturer 等）
        'oc_product',

        // 9. 产品描述表（依赖 oc_product）
        'oc_product_description',

        // 10. 产品属性表（依赖 oc_product）
        'oc_product_attribute',

        // 11. 产品折扣表（依赖 oc_product）
        'oc_product_discount',

        // 12. 产品过滤器表（依赖 oc_product）
        'oc_product_filter',

        // 13. 产品图片表（依赖 oc_product）
        'oc_product_image',

        // 14. 产品选项表（依赖 oc_product, oc_option）
        'oc_product_option',

        // 15. 产品选项值表（依赖 oc_product, oc_product_option, oc_option_value）
        'oc_product_option_value',

        // 16. 产品周期表（依赖 oc_product）
        'oc_product_recurring',

        // 17. 产品关联表（依赖 oc_product）
        'oc_product_related',

        // 18. 产品奖励表（依赖 oc_product）
        'oc_product_reward',

        // 19. 产品特价表（依赖 oc_product）
        'oc_product_special',

        // 20. 产品分类关联表（依赖 oc_product, oc_category）
        'oc_product_to_category',

        // 21. 产品下载关联表（依赖 oc_product）
        'oc_product_to_download',

        // 22. 产品布局关联表（依赖 oc_product）
        'oc_product_to_layout',

        // 23. 产品商店关联表（依赖 oc_product）
        'oc_product_to_store',
    ],

    // ========== 外键关联配置 ==========
    // 格式：'子表' => ['外键字段' => '引用表']
    'foreign_keys' => [
        // 分类相关外键
        'oc_category_description' => [
            'category_id' => 'oc_category',
        ],
        'oc_category_filter' => [
            'category_id' => 'oc_category',
        ],
        'oc_category_path' => [
            'category_id' => 'oc_category',
            'path_id' => 'oc_category',
        ],
        'oc_category_to_layout' => [
            'category_id' => 'oc_category',
        ],
        'oc_category_to_store' => [
            'category_id' => 'oc_category',
        ],

        // 选项相关外键
        'oc_option_description' => [
            'option_id' => 'oc_option',
        ],
        'oc_option_value' => [
            'option_id' => 'oc_option',
        ],
        'oc_option_value_description' => [
            'option_value_id' => 'oc_option_value',
            'option_id' => 'oc_option',
        ],

        // 产品相关外键
        'oc_product_description' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_attribute' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_discount' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_filter' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_image' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_option' => [
            'product_id' => 'oc_product',
            'option_id' => 'oc_option',
        ],
        'oc_product_option_value' => [
            'product_id' => 'oc_product',
            'product_option_id' => 'oc_product_option',
            'option_value_id' => 'oc_option_value',
        ],
        'oc_product_recurring' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_related' => [
            'product_id' => 'oc_product',
            'related_id' => 'oc_product',
        ],
        'oc_product_reward' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_special' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_to_category' => [
            'product_id' => 'oc_product',
            'category_id' => 'oc_category',
        ],
        'oc_product_to_download' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_to_layout' => [
            'product_id' => 'oc_product',
        ],
        'oc_product_to_store' => [
            'product_id' => 'oc_product',
        ],
    ],

    // ========== 非自增主键表 ==========
    // 这些表的主键不是自增ID，插入时需要保留原主键值
    'non_auto_increment_tables' => [
        'oc_category_description',
        'oc_category_filter',
        'oc_category_path',
        'oc_category_to_layout',
        'oc_category_to_store',
        'oc_option_description',
        'oc_option_value_description',
        'oc_product_description',
        'oc_product_attribute',
        'oc_product_filter',
        'oc_product_recurring',
        'oc_product_related',
        'oc_product_to_category',
        'oc_product_to_download',
        'oc_product_to_layout',
        'oc_product_to_store',
    ],

    // ========== 全量同步表 ==========
    // 这些表使用全量对比模式，而不是增量同步
    // 即使是单主键表，也会进行全量对比
    'full_sync_tables' => [
        //'oc_product',
    ],

    // ========== 超时配置 ==========
    'connection_timeout' => 3,    // 数据库连接超时（秒）
    'query_timeout' => 300,       // 查询超时（秒）

    // ========== 并发配置 ==========
    'concurrency' => [
        'max_concurrent_dbs' => 13,  // 最大并发数据库数
    ],

    // ========== IP重试配置 ==========
    'ip_retry' => [
        'max_retries' => 3,         // 最大重试次数
    ],

    // ========== ID映射配置 ==========
    'mappings' => [
        // JSON映射文件（运行时映射）
        'json_file' => storage_path('app/remote_product_sync/id_mappings.json'),

        // CSV进度文件（增量同步）
        'progress_file' => storage_path('app/remote_product_sync/progress.csv'),

        // 历史备份目录
        'history_dir' => storage_path('app/remote_product_sync/history'),

        // 历史备份保留天数
        'history_retention_days' => 7,

        // IP缓存文件
        'ip_cache_file' => storage_path('app/remote_product_sync/ip_cache.json'),
    ],

    // ========== IP缓存配置 ==========
    'ip_cache_ttl' => 3600,  // IP缓存有效期（秒），默认1小时

    // ========== 日志配置 ==========
    'logging' => [
        // 日志目录
        'log_dir' => storage_path('logs/remote_product_sync'),
    ],

    // ========== 数据完整性配置 ==========
    'data_integrity' => [
        // 是否自动修复缺失的子表数据
        'auto_fix_missing_child_data' => true,

        // 是否删除本地多余的记录（远程已删除的记录）
        'delete_local_orphaned_records' => true,

        // 需要检查完整性的子表（主表 => 子表）
        'child_tables_to_check' => [
            // oc_product 系列
            'oc_product' => [
                'oc_product_description' => [
                    'foreign_key' => 'product_id',
                    'default_values' => [
                        'language_id' => 1,
                        'name' => '{model}',
                        'description' => '',
                        'tag' => '',
                        'meta_title' => '{model}',
                        'meta_description' => '',
                        'meta_keyword' => '',
                    ],
                ],
                'oc_product_to_store' => [
                    'foreign_key' => 'product_id',
                    'default_values' => ['store_id' => 0],
                ],
                'oc_product_to_layout' => [
                    'foreign_key' => 'product_id',
                    'default_values' => ['store_id' => 0, 'layout_id' => 0],
                ],
                'oc_product_to_category' => [
                    'foreign_key' => 'product_id',
                    'default_values' => ['category_id' => 0],
                ],
            ],
            // oc_category 系列
            'oc_category' => [
                'oc_category_to_store' => [
                    'foreign_key' => 'category_id',
                    'default_values' => ['store_id' => 0],
                ],
                'oc_category_to_layout' => [
                    'foreign_key' => 'category_id',
                    'default_values' => ['store_id' => 0, 'layout_id' => 0],
                ],
            ],
            // oc_option 系列
            'oc_option' => [
                'oc_option_description' => [
                    'foreign_key' => 'option_id',
                    'default_values' => [
                        'language_id' => 1,
                        'name' => '{name}',
                    ],
                ],
            ],
            // oc_option_value 系列
            'oc_option_value' => [
                'oc_option_value_description' => [
                    'foreign_key' => 'option_value_id',
                    'default_values' => [
                        'language_id' => 1,
                        'option_id' => '{option_id}',
                        'name' => '{name}',
                    ],
                ],
            ],
        ],
    ],
];
