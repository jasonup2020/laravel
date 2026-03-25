<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'cloudflare' => [
        'api_key' => env('CLOUDFLARE_API_KEY', '656bdc8fff31f1****bfc1de7d780aa03a55e'),
        'api_email' => env('CLOUDFLARE_API_EMAIL', 'savebullet@outlook.com'),
        'token' => env('CLOUDFLARE_TOKEN', '6sw7i5oNVlNKJBTo****X33CcxPBar_PkgbNe9tC'),
        'user_service_key' => env('CLOUDFLARE_USER_SERVICE_KEY', ''),
        'api' => env('CLOUDFLARE_API', 'https://api.cloudflare.com/client/v4'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID', ''),
        'zone_id' => env('CLOUDFLARE_ZONE_ID', ''),
    ],

    'huawei' => [
        'dns' => [
            'url' => env('HUAWEI_DNS_URL', 'https://cdn.myhuaweicloud.com'),
            'project_id' => env('HUAWEI_DNS_PROJECT_ID', 'aa2d4ab8a8****c3919fd29bc3802b8f'),
            'service_name' => env('HUAWEI_DNS_SERVICE_NAME', 'url_dns'),
            'access_key' => env('HUAWEI_ACCESS_KEY', 'HPUAF09AD****EMQ9YJH'),
            'secret_key' => env('HUAWEI_SECRET_KEY', '39TZxUGHbRvKSWIU****vdAEoNrRv65KeSB4OD8E'),
            'region' => env('HUAWEI_REGION', 'cn-north-4'),
            'iam_url' => env('HUAWEI_IAM_URL', 'https://iam.cn-north-4.myhuaweicloud.com/v3/auth/tokens'),
            'cache_key' => env('HUAWEI_CACHE_KEY', 'huawei_cloud_token'),
            'cache_expire' => env('HUAWEI_CACHE_EXPIRE', 43200),
        ],
        'img' => [
            'url' => env('HUAWEI_IMG_URL', 'https://mms.cn-north-4.myhuaweicloud.com'),
            'project_id' => env('HUAWEI_IMG_PROJECT_ID', 'aa2d4ab8a8****c3919fd29bc3802b8f'),
            'service_name' => env('HUAWEI_IMG_SERVICE_NAME', 'image_search'),
            'access_key' => env('HUAWEI_ACCESS_KEY', 'HPUAF09ADNYD8EMQ9YJH'),
            'secret_key' => env('HUAWEI_SECRET_KEY', '39TZxUGHbRvKSWIU8****dAEoNrRv65KeSB4OD8E'),
            'region' => env('HUAWEI_REGION', 'cn-north-4'),
            'iam_url' => env('HUAWEI_IAM_URL', 'https://iam.cn-north-4.myhuaweicloud.com/v3/auth/tokens'),
            'cache_key' => env('HUAWEI_CACHE_KEY', 'huawei_cloud_token'),
            'cache_expire' => env('HUAWEI_CACHE_EXPIRE', 43200),
        ],
    ],

];
