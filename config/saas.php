<?php

/**
 * SaaS 平台配置文件
 * 
 * 包含多租户、认证、权限、API等核心配置
 * 
 * @package Config
 * @author  SaaS Platform
 * @version 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 多租户配置
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        // 是否启用多租户
        'enabled' => env('SAAS_TENANT_ENABLED', true),
        
        // 租户隔离方式：database（数据库隔离）、domain（域名隔离）、column（字段隔离）
        'isolation_mode' => env('SAAS_TENANT_ISOLATION_MODE', 'column'),
        
        // 租户数据库前缀
        'database_prefix' => env('SAAS_TENANT_DATABASE_PREFIX', 'tenant_'),
        
        // 租户域名后缀
        'domain_suffix' => env('SAAS_TENANT_DOMAIN_SUFFIX', ''),
        
        // 租户ID字段名
        'tenant_id_column' => 'tenant_id',
        
        // 自动创建租户数据库
        'auto_create_database' => env('SAAS_TENANT_AUTO_CREATE_DATABASE', false),
        
        // 租户中间件
        'middleware' => [
            'identify' => \App\Http\Middleware\IdentifyTenant::class,
            'verify' => \App\Http\Middleware\VerifyTenant::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 认证配置
    |--------------------------------------------------------------------------
    */
    'auth' => [
        // JWT配置
        'jwt' => [
            // Access Token 有效期（分钟）
            'access_token_ttl' => env('JWT_ACCESS_TOKEN_TTL', 120),
            
            // Refresh Token 有效期（分钟）
            'refresh_token_ttl' => env('JWT_REFRESH_TOKEN_TTL', 10080), // 7天
            
            // Token 前缀
            'token_prefix' => 'Bearer',
            
            // Token 存储键前缀
            'token_cache_prefix' => 'saas:token:',
            
            // 是否启用Token黑名单
            'enable_blacklist' => true,
            
            // 黑名单缓存时间（秒）
            'blacklist_ttl' => env('JWT_BLACKLIST_TTL', 604800), // 7天
        ],
        
        // 单设备登录
        'single_device_login' => [
            // 是否启用
            'enabled' => env('SINGLE_DEVICE_LOGIN_ENABLED', false),
            
            // 新登录时是否踢出旧设备
            'kick_out_old_device' => true,
            
            // 设备Token缓存键前缀
            'device_token_prefix' => 'saas:device:token:',
        ],
        
        // 密码配置
        'password' => [
            // 最小长度
            'min_length' => 6,
            
            // 最大长度
            'max_length' => 20,
            
            // 是否要求包含数字
            'require_number' => false,
            
            // 是否要求包含字母
            'require_letter' => false,
            
            // 是否要求包含特殊字符
            'require_special_char' => false,
        ],
        
        // 登录配置
        'login' => [
            // 最大尝试次数
            'max_attempts' => 5,
            
            // 锁定时间（分钟）
            'lockout_duration' => 15,
            
            // 验证码开关
            'captcha_enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 权限配置
    |--------------------------------------------------------------------------
    */
    'permission' => [
        // 是否启用权限检查
        'enabled' => env('PERMISSION_ENABLED', true),
        
        // 超级管理员角色标识
        'super_admin_role' => 'super_admin',
        
        // 权限缓存时间（秒）
        'cache_ttl' => 3600,
        
        // 权限缓存键前缀
        'cache_prefix' => 'saas:permission:',
        
        // 白名单路由（不需要权限检查）
        'whitelist_routes' => [
            'api/v1/auth/login',
            'api/v1/auth/register',
            'api/v1/auth/refresh',
            'api/v1/auth/logout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API配置
    |--------------------------------------------------------------------------
    */
    'api' => [
        // API版本
        'version' => 'v1',
        
        // API前缀
        'prefix' => 'api',
        
        // 默认分页大小
        'default_per_page' => 15,
        
        // 最大分页大小
        'max_per_page' => 100,
        
        // 响应格式
        'response' => [
            // 成功状态码
            'success_code' => 200,
            
            // 错误状态码
            'error_code' => 400,
            
            // 未授权状态码
            'unauthorized_code' => 401,
            
            // 禁止访问状态码
            'forbidden_code' => 403,
            
            // 未找到状态码
            'not_found_code' => 404,
            
            // 验证失败状态码
            'validation_error_code' => 422,
            
            // 服务器错误状态码
            'server_error_code' => 500,
        ],
        
        // 限流配置
        'rate_limit' => [
            // 是否启用
            'enabled' => true,
            
            // 默认限制（每分钟请求次数）
            'default_limit' => 60,
            
            // 登录接口限制
            'login_limit' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存配置
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // 默认缓存时间（秒）
        'default_ttl' => 3600,
        
        // 缓存键前缀
        'prefix' => 'saas:',
        
        // 用户权限缓存时间
        'user_permission_ttl' => 3600,
        
        // 用户信息缓存时间
        'user_info_ttl' => 1800,
        
        // 菜单缓存时间
        'menu_ttl' => 3600,
        
        // 配置缓存时间
        'config_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | 队列配置
    |--------------------------------------------------------------------------
    */
    'queue' => [
        // 默认队列名称
        'default' => 'default',
        
        // 邮件队列
        'mail' => 'mail',
        
        // 通知队列
        'notification' => 'notification',
        
        // 日志队列
        'log' => 'log',
    ],

    /*
    |--------------------------------------------------------------------------
    | 文件上传配置
    |--------------------------------------------------------------------------
    */
    'upload' => [
        // 默认上传磁盘
        'disk' => env('UPLOAD_DISK', 'local'),
        
        // 最大文件大小（字节）
        'max_size' => env('UPLOAD_MAX_SIZE', 10485760), // 10MB
        
        // 允许的文件类型
        'allowed_types' => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
            'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv'],
            'audio' => ['mp3', 'wav', 'ogg', 'flac'],
        ],
        
        // 图片最大尺寸
        'image_max_dimension' => [
            'width' => 2000,
            'height' => 2000,
        ],
        
        // 缩略图尺寸
        'thumbnail_dimensions' => [
            'small' => ['width' => 100, 'height' => 100],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 600, 'height' => 600],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    */
    'log' => [
        // 是否记录请求日志
        'request_log_enabled' => env('REQUEST_LOG_ENABLED', true),
        
        // 是否记录响应日志
        'response_log_enabled' => env('RESPONSE_LOG_ENABLED', false),
        
        // 是否记录SQL日志
        'sql_log_enabled' => env('SQL_LOG_ENABLED', false),
        
        // 敏感字段（不记录到日志）
        'sensitive_fields' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret',
        ],
        
        // 日志保留天数
        'keep_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全配置
    |--------------------------------------------------------------------------
    */
    'security' => [
        // XSS防护
        'xss' => [
            'enabled' => true,
            'allowed_tags' => [],
        ],
        
        // SQL注入防护
        'sql_injection' => [
            'enabled' => true,
        ],
        
        // CORS配置
        'cors' => [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['*'],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ],
        
        // IP白名单
        'ip_whitelist' => [
            'enabled' => false,
            'ips' => [],
        ],
        
        // IP黑名单
        'ip_blacklist' => [
            'enabled' => false,
            'ips' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 设备管理配置
    |--------------------------------------------------------------------------
    */
    'device' => [
        // 是否启用设备管理
        'enabled' => true,
        
        // 设备Token缓存键前缀
        'cache_prefix' => 'saas:device:',
        
        // 设备在线状态缓存时间（秒）
        'online_ttl' => 300,
        
        // 最大设备数量
        'max_devices' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | 多语言配置
    |--------------------------------------------------------------------------
    */
    'i18n' => [
        // 默认语言
        'default_locale' => 'zh',
        
        // 支持的语言
        'supported_locales' => ['zh', 'en', 'ko'],
        
        // 语言映射
        'locale_names' => [
            'zh' => '中文',
            'en' => 'English',
            'ko' => '한국어',
        ],
    ],
];
