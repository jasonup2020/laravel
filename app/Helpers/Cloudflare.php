<?php
/**
 * Cloudflare API 辅助工具类
 * 
 * 提供与Cloudflare API交互的核心功能，包括账户管理、DNS记录操作、
 * 区域(Zone)管理等功能的封装，简化API调用流程
 * 
 * @package App\Helpers
 * @author Administrator
 * @version 1.0
 */
namespace App\Helpers;


use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare API交互类
 * 
 * 实现Cloudflare API的认证、请求发送及常用功能封装
 */
class Cloudflare {

    /**
     * API密钥 (X-Auth-Key)
     * @var string
     */
    protected $api_key = "";  
    
    /**
     * 用户服务密钥 (X-Auth-User-Service-Key)
     * @var string
     */
    protected $user_service_key = "";  
    
    /**
     * API邮箱 (X-Auth-Email)
     * @var string
     */
    protected $api_email = ""; 
    
    /**
     * 认证令牌 (Authorization: Bearer)
     * @var string
     */
    protected $token = ""; 
    
    /**
     * Cloudflare API基础URL
     * @var string
     */
    protected $api = "https://api.cloudflare.com/client/v4";
    
    /**
     * 账户ID
     * @var string
     */
    protected $account_id = ""; 
    
    /**
     * 区域ID
     * @var string
     */
    protected $zone_id = '';

    /**
     * 构造函数：初始化Cloudflare API认证信息
     * 如果希望多台面板，可以在实例化对象时，将面板地址与密钥传入
     * @param string|null $api_key API密钥
     * @param string|null $api_email API邮箱
     * @param string|null $user_service_key 用户服务密钥
     * @param string $token 认证令牌
     * @param string $account_id 账户ID
     */
    public function __construct($api_key = null, $api_email = null, $user_service_key = null, $token = '', $account_id = '') {


        $this->api_key = $api_key ?: config('helperservice.cloudflare.api_key', '');
        $this->user_service_key = $user_service_key ?: config('helperservice.cloudflare.user_service_key', '');
        $this->api_email = $api_email ?: config('helperservice.cloudflare.api_email', '');
        $this->token = $token ?: config('helperservice.cloudflare.token', '');
        $this->api = config('helperservice.cloudflare.api', 'https://api.cloudflare.com/client/v4');
        $this->account_id = $account_id ?: config('helperservice.cloudflare.account_id', '');
    }

    /**
     * 001 获取账户ID
     * 
     * 如果未设置账户ID，则自动调用getAccounts()获取并设置默认账户ID
     * 
     * @return string 账户ID
     */
    public function getAccountId() {
        if (!$this->account_id) {
            $this->getAccounts();
        }

        return $this->account_id;
    }

    /**
     * 002 验证令牌有效性
     * 
     * @param string $account_id 账户ID
     * @return array API响应结果
     */
    public function tokenVerify($account_id) {
        $url = rtrim($this->api, '/') . '/accounts/' . $account_id . '/tokens/verify';
        return $this->request($url, [], 'GET', ['access_token' => $this->token]);
    }

    /**
     * 003 获取账户列表
     * 
     * 获取当前认证用户下的所有Cloudflare账户，并设置默认账户ID
     * 
     * @return array 账户列表数组
     */
    public function getAccounts() {
        $url = rtrim($this->api, '/') . '/accounts';

        $result = $this->request($url, [], 'GET', ['access_token' => $this->token]);
        $list = $result['result'] ?? [];
        if (!$this->account_id && $list) {
            $this->account_id = $list[0]['id'] ?? [];
        }
        return $list;
    }

    /**
     * 发送API请求
     * 
     * 封装通用的API请求逻辑，处理认证头、请求参数和响应格式化
     * 
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param string $method 请求方法(GET/POST/PUT/DELETE)
     * @param array $params 请求参数
     * @return array API响应结果
     * @throws \Exception 当请求失败时抛出异常
     */
    public function request($url, $data, $method = 'GET', $params = ['access_token' => '']) {
        $headers = array_merge($params['header'] ?? [], [
                //'Content-Type' => 'application/json',
                //'Accept' => 'application/json'
        ]);

        if ($this->token) {
            //$headers['Authorization'] = trim('Bearer ' . $this->token);
            $params['access_token'] = $this->token;
        }

        if ($this->api_key) {
            $headers['X-Auth-Key'] = $this->api_key;
        }

        if ($this->api_email) {
            $headers['X-Auth-Email'] = $this->api_email;
        }

        if ($this->user_service_key) {
            $headers['X-Auth-User-Service-Key'] = $this->api_email;
        }

        $params = array_merge($params, ['format' => 'json', 'header' => $headers, 'timeout' => 60]);
        $result = Http::httpRequest($url, $data, $method, $params);
        //$result = (new Http())->request($method, $url, json_encode($data), $headers);

//        Log::Info($url, ['data' => $data, 'result' => $result], 'site_daily');
        Log::Info($url, ['data' => $data, 'result' => $result], 'site_daily');
        
//        Log::Info("request_request", ['data' => $data, 'result' => $result,"url"=>$url, "data"=>$data, "method"=>$method , "params"=>$params], 'site_daily');
        if (!$result) {
            throw new \Exception('请求失败', 40004);
        }
        return $result;
    }
    
    
    
    /**
     * 005 设置区域ID
     * 
     * @param string $zone_id 区域ID
     * @return $this
     */
    public function setZoneId($zone_id)
    {
        $this->zone_id = $zone_id;
        return $this;
    }

    /**
     * 006 获取区域ID
     * 
     * @return string 区域ID
     */
    public function getZoneId()
    {
        return $this->zone_id;
    }

    /**
     * 007获取区域列表
     * 
     * @param string $domain 可选域名过滤
     * @return array 区域列表
     */
    public function list($domain = '')
    {
        $url = rtrim($this->api, '/') . '/zones';

        $params = [];
        if ($domain) {
            $params['name'] = $domain;
        }

        $res = $this->request($url, $params, 'GET', ['access_token' => $this->token]);

        $success = $res['success'] ?? false;
        $result = $res['result'] ?? [];
        return $result ?: [];
    }

    /**
     * 007.01 获取区域详细信息
     * 
     * @return array 区域详细信息
     */
    public function detail()
    {
        $url = rtrim($this->api, '/') . '/zones/' . $this->zone_id;

        $res = $this->request($url, [], 'GET', ['access_token' => $this->token]);
        //var_dump($res, $url);exit;

        $success = $res['success'] ?? false;
        $messages = $res['messages'] ?? [];
        $errors = $res['errors'] ?? [];
        $result = $res['result'] ?? [];
        //status : initializing,active,pending
        return $result ?: [];
    }

    /**
     * 004 添加新区域
     * 
     * @param string $domain 域名
     * @param string $type 区域类型(full/partial/secondary)
     * @return array API响应结果
     */
    public function add($domain, $type = 'full')
    {
        $url = rtrim($this->api, '/') . '/zones';
        $res = $this->request($url, [
            'account' => ['id' => $this->getAccountId()],
            'name' => $domain,
            'jump_start' => true,
            'type' => $type, //full, partial, secondary
        ], 'POST', ['access_token' => $this->token]);

        $success = $res['success'] ?? false;
        $result = $res['result'] ?? [];
        if ($result) {
            $this->setZoneId($result['id'] ?? '');
        }
        return $res ?: [];
    }

    /**
     * 获取DNS记录列表
     * 
     * @param string $name 可选名称过滤 www.motor-skirt.com
     * @param string $content 可选内容过滤 198.51.100.4
     * @return array DNS记录列表
     */
    public function getDNSRecords($name = '', $content = '')
    {
        $url = rtrim($this->api, '/') . '/zones/' . $this->zone_id . '/dns_records';
        $params = [];
        if ($content) {
            $params['content'] = $content;
        }

        if ($name) {
            $params['name'] = $content;
        }

        $res = $this->request($url, $params, 'GET', []);

        $success = $res['success'] ?? false;
        $list = $res['result'] ?? [];
        return $list;
    }

    /**
     * 添加DNS记录
     * 
     * @param string $name 记录名称   www.motor-skirt.com
     * @param string $content 记录内容 198.51.100.4
     * @param string $type 记录类型(A/AAAA/CNAME等)
     * @return array API响应结果
     */
    public function addDNSRecord($name, $content, $type = 'A')
    {
        $url = rtrim($this->api, '/') . '/zones/' . $this->zone_id . '/dns_records';
        return $this->request($url, [
            'comment' => "",
            'name' => $name, // www, @ , example.com
            'proxied' => true,
            //'settings' => '{}',
            'tags' => [],
            'ttl' => 900,
            'content' => $content, //198.51.100.4
            'type' => $type,
        ], 'POST', []);
    }

    /**
     * 批量处理DNS记录
     * 
     * 支持批量删除、添加、修改和查询DNS记录
     * 
     * @param array $deletes 待删除记录ID数组
     * @param array $posts 待添加记录数组
     * @param array $patches 待修改记录数组
     * @param array $puts 待查询记录数组
     * @return array API响应结果
     */
    public function batchDNSRecord($deletes = [], $posts = [], $patches = [], $puts = [])
    {
        $url = rtrim($this->api, '/') . '/zones/' . $this->zone_id . '/dns_records/batch';
        $params = [];
        if ($deletes) {
            //批量删除
            $params['deletes'] = $deletes; //[['id' => '023e105f4ecef8ad9ca31a8372d0c353']]
        }

        if ($posts) {
            //批量添加
            $params['posts'] = $posts; //[['comment' => '', 'name' => '', 'proxied' => true, 'settings' => '{}', 'type' => 'A', 'content' => '']]
        }

        if ($patches) {
            //批量修改
            $params['patches'] = $patches;
        }

        if ($puts) {
            //批量查询
            $params['puts'] = $puts;
        }

        return $this->request($url, $params, 'POST', []);
    }


    /**
     * 修改Cloudflare区域设置
     * 
     * @param string $setting_id 设置ID(如rum, 0rtt, always_use_https等)
     * @param string $value 设置值
     * @return array API响应结果
     */
    public function settings($setting_id, $value = '')
    {
        //rum，0rtt，always_use_https，early_hints，ssl
        $url = rtrim($this->api, '/') . '/zones/' . $this->zone_id . '/settings/' . $setting_id;
        return $this->request($url, [
            'value' => $value ?: "on",
        ], 'PATCH', ['access_token' => $this->token]);
    }

    /**
     * 设置SSL/TLS加密模式
     * 
     * @param string $value 加密模式(off, flexible, full, strict)
     * @return array API响应结果
     */
    public function settingSSL($value = '')
    {
        return $this->settings('ssl', $value ?: 'flexible'); //off, flexible, full, strict
    }

    /**
     * 批量修改区域设置
     * 
     * 同时修改多项常用设置，包括RUM、0-RTT、HTTPS强制跳转和Early Hints
     * 
     * @param string $value 设置值(on/off或具体值)
     * @return array 各项设置的API响应结果数组
     */
    public function settingBatch($value = '')
    {
        $res1 = $this->settings('rum', $value ?: 'on');
        $res2 = $this->settings('0rtt', $value ?: 'on');
        $res3 = $this->settings('always_use_https', $value ?: 'on');
        $res4 = $this->settings('early_hints', $value ?: 'on');
        return [
            'res1' => $res1,
            'res2' => $res2,
            'res3' => $res3,
            'res4' => $res4,
        ];
    }
    
 
    
    
    

}
