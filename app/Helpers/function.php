<?php

/**
 * 全局辅助函数文件
 * 
 * 提供常用的辅助函数，用于简化开发
 * 
 * @package App\Helpers
 * @author  SaaS Platform
 * @version 1.0.0
 */

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

if (!function_exists('generate_random_code')) {
    /**
     * 生成随机验证码
     *
     * @param int $length 长度，默认6位
     * @return string
     */
    function generate_random_code(int $length = 6): string
    {
        return Str::random($length);
    }
}

if (!function_exists('encrypt_password')) {
    /**
     * 加密密码（使用随机盐值）
     *
     * @param string $password 原始密码
     * @return array ['password' => 加密后密码, 'salt' => 盐值]
     */
    function encrypt_password(string $password): array
    {
        $salt = generate_random_code(6);
        $encryptedPassword = \Illuminate\Support\Facades\Hash::make($password . $salt);
        
        return [
            'password' => $encryptedPassword,
            'salt' => $salt
        ];
    }
}

if (!function_exists('verify_password')) {
    /**
     * 验证密码
     *
     * @param string $password 原始密码
     * @param string $hashedPassword 加密后的密码
     * @param string $salt 盐值
     * @return bool
     */
    function verify_password(string $password, string $hashedPassword, string $salt): bool
    {
        return \Illuminate\Support\Facades\Hash::check($password . $salt, $hashedPassword);
    }
}

if (!function_exists('generate_order_no')) {
    /**
     * 生成订单号
     *
     * @param string $prefix 前缀
     * @return string
     */
    function generate_order_no(string $prefix = ''): string
    {
        return $prefix . date('YmdHis') . Str::random(6);
    }
}

if (!function_exists('format_size')) {
    /**
     * 格式化文件大小
     *
     * @param int $size 字节数
     * @return string
     */
    function format_size(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}

if (!function_exists('mask_phone')) {
    /**
     * 手机号脱敏
     *
     * @param string $phone 手机号
     * @return string
     */
    function mask_phone(string $phone): string
    {
        if (strlen($phone) !== 11) {
            return $phone;
        }
        
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }
}

if (!function_exists('mask_email')) {
    /**
     * 邮箱脱敏
     *
     * @param string $email 邮箱
     * @return string
     */
    function mask_email(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        $nameLength = strlen($name);
        
        if ($nameLength <= 2) {
            $maskedName = $name[0] . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', $nameLength - 2);
        }
        
        return $maskedName . '@' . $domain;
    }
}

if (!function_exists('mask_id_card')) {
    /**
     * 身份证号脱敏
     *
     * @param string $idCard 身份证号
     * @return string
     */
    function mask_id_card(string $idCard): string
    {
        $length = strlen($idCard);
        if ($length < 8) {
            return $idCard;
        }
        
        return substr($idCard, 0, 4) . str_repeat('*', $length - 8) . substr($idCard, -4);
    }
}

if (!function_exists('is_json')) {
    /**
     * 判断字符串是否为JSON
     *
     * @param string $string 字符串
     * @return bool
     */
    function is_json(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    function get_client_ip(): string
    {
        $request = request();
        
        if ($request->header('x-forwarded-for')) {
            $ips = explode(',', $request->header('x-forwarded-for'));
            return trim($ips[0]);
        }
        
        if ($request->header('x-real-ip')) {
            return $request->header('x-real-ip');
        }
        
        return $request->ip() ?? '127.0.0.1';
    }
}

if (!function_exists('get_distance')) {
    /**
     * 计算两个坐标点之间的距离（单位：公里）
     *
     * @param float $lat1 纬度1
     * @param float $lng1 经度1
     * @param float $lat2 纬度2
     * @param float $lng2 经度2
     * @return float
     */
    function get_distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // 地球半径（公里）
        
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $latDiff = deg2rad($lat2 - $lat1);
        $lngDiff = deg2rad($lng2 - $lng1);
        
        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos($lat1Rad) * cos($lat2Rad) * sin($lngDiff / 2) * sin($lngDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}

if (!function_exists('tree_to_list')) {
    /**
     * 树形结构转列表
     *
     * @param array $tree 树形数据
     * @param string $childrenKey 子节点键名
     * @return array
     */
    function tree_to_list(array $tree, string $childrenKey = 'children'): array
    {
        $list = [];
        
        foreach ($tree as $item) {
            $children = $item[$childrenKey] ?? [];
            unset($item[$childrenKey]);
            
            $list[] = $item;
            
            if (!empty($children)) {
                $list = array_merge($list, tree_to_list($children, $childrenKey));
            }
        }
        
        return $list;
    }
}

if (!function_exists('list_to_tree')) {
    /**
     * 列表转树形结构
     *
     * @param array $list 列表数据
     * @param string $idKey ID键名
     * @param string $parentKey 父ID键名
     * @param string $childrenKey 子节点键名
     * @param mixed $parentId 父ID值
     * @return array
     */
    function list_to_tree(
        array $list,
        string $idKey = 'id',
        string $parentKey = 'parent_id',
        string $childrenKey = 'children',
        $parentId = 0
    ): array {
        $tree = [];
        
        foreach ($list as $item) {
            if ($item[$parentKey] == $parentId) {
                $children = list_to_tree($list, $idKey, $parentKey, $childrenKey, $item[$idKey]);
                
                if (!empty($children)) {
                    $item[$childrenKey] = $children;
                }
                
                $tree[] = $item;
            }
        }
        
        return $tree;
    }
}

if (!function_exists('cache_remember')) {
    /**
     * 缓存记住（简化版）
     *
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int $ttl 过期时间（秒）
     * @return mixed
     */
    function cache_remember(string $key, callable $callback, int $ttl = 3600)
    {
        return Cache::remember($key, $ttl, $callback);
    }
}

if (!function_exists('format_duration')) {
    /**
     * 格式化时长（秒转时分秒）
     *
     * @param int $seconds 秒数
     * @return string
     */
    function format_duration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        
        return sprintf('%02d:%02d', $minutes, $secs);
    }
}

if (!function_exists('generate_uuid')) {
    /**
     * 生成UUID
     *
     * @return string
     */
    function generate_uuid(): string
    {
        return Str::uuid()->toString();
    }
}

if (!function_exists('is_mobile')) {
    /**
     * 判断是否为手机号
     *
     * @param string $phone 手机号
     * @return bool
     */
    function is_mobile(string $phone): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $phone) === 1;
    }
}

if (!function_exists('is_email')) {
    /**
     * 判断是否为邮箱
     *
     * @param string $email 邮箱
     * @return bool
     */
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('is_id_card')) {
    /**
     * 判断是否为身份证号
     *
     * @param string $idCard 身份证号
     * @return bool
     */
    function is_id_card(string $idCard): bool
    {
        // 15位或18位身份证号
        return preg_match('/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/', $idCard) === 1;
    }
}

if (!function_exists('array_to_xml')) {
    /**
     * 数组转XML
     *
     * @param array $data 数据
     * @param string $root 根节点名称
     * @return string
     */
    function array_to_xml(array $data, string $root = 'xml'): string
    {
        $xml = '<' . $root . '>';
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= '<' . $key . '>' . array_to_xml($value, $key) . '</' . $key . '>';
            } else {
                $xml .= '<' . $key . '><![CDATA[' . $value . ']]></' . $key . '>';
            }
        }
        
        $xml .= '</' . $root . '>';
        
        return $xml;
    }
}

if (!function_exists('xml_to_array')) {
    /**
     * XML转数组
     *
     * @param string $xml XML字符串
     * @return array
     */
    function xml_to_array(string $xml): array
    {
        libxml_disable_entity_loader(true);
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        return json_decode(json_encode($data), true);
    }
}
