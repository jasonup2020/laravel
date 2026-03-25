<?php

namespace App\Helpers;

use App\Helpers\Open_Redis;
use Illuminate\Support\Facades\Log;

/**
 * 华为云图片服务助手类
 * 功能：封装华为云图片搜索服务的核心操作，包括Token管理、图片数据上传、搜索、更新等
 * 新增：公共PostAPI提交方法 `postApi`，支持统一处理API请求
 */
class HuaWei_Dns {

    /** @var Open_Redis Redis缓存实例 */
    private $redis;

    /** @var string Redis缓存键名（存储华为云认证Token） */
    private $cacheKey;

    /** @var int 缓存过期时间（12小时，单位：秒） */
    private $cacheExpire;

    /** @var string 华为云图片服务基础URL */
    private $url;

    /** @var string 华为云项目ID */
    private $project_id;

    /** @var string 华为云服务名称（固定为图片搜索服务） */
    private $service_name;

    /** @var string 华为云Access Key */
    private $access_key;

    /** @var string 华为云Secret Key */
    private $secret_key;

    /** @var string 华为云区域 */
    private $region;

    /** @var string 华为云IAM URL */
    private $iam_url;

    /**
     * 构造函数：初始化Redis缓存实例和配置
     */
    public function __construct() {
        $this->redis = new Open_Redis();

        // 从配置文件加载配置
        $this->url = config('helperservice.huawei.dns.url', 'https://cdn.myhuaweicloud.com');
        $this->project_id = config('helperservice.huawei.dns.project_id', 'aa2d4ab8a8d****3919fd29bc3802b8f');
        $this->service_name = config('helperservice.huawei.dns.service_name', 'url_dns');
        $this->access_key = config('helperservice.huawei.dns.access_key', 'HPUAF09ADNYD8EMQ9YJH');
        $this->secret_key = config('helperservice.huawei.dns.secret_key', '39TZxUGHbRvKSWIU8****dAEoNrRv65KeSB4OD8E');
        $this->region = config('helperservice.huawei.dns.region', 'cn-north-4');
        $this->iam_url = config('helperservice.huawei.dns.iam_url', 'https://iam.cn-north-4.myhuaweicloud.com/v3/auth/tokens');
        $this->cacheKey = config('helperservice.huawei.dns.cache_key', 'huawei_cloud_token');
        $this->cacheExpire = config('helperservice.huawei.dns.cache_expire', 43200);
    }

    private $force = false;

    /**
     * 设置华为云服务名称（固定为图片搜索服务）默认:url_dns
     * @param type $service_name
     */
    public function setServiceName($service_name = "url_dns") {
        if (!empty($service_name)) {
            $this->service_name = trim($service_name);
        }
    }

    /**
     * 获得华为云服务名称（固定为图片搜索服务）默认:url_dns
     * @param type $service_name
     */
    public function getServiceName() {
        return $this->service_name;
    }

    /**
     * 通用API请求方法，支持GET和POST提交，默认使用POST
     * @param string $url 请求URL地址
     * @param mixed $data 请求数据（数组或字符串）
     * @param array $headers 请求头信息
     * @param bool $returnHeader 是否返回响应头
     * @param string $method 请求方法（GET或POST），默认为POST
     * @return array 响应结果：content（响应内容）、code（HTTP状态码）、error（错误信息）
     * @example 调用示例：
     *          $result = $huawei->postApi('https://api.example.com', ['key' => 'value'], ['Content-Type: application/json']);
     *          $result = $huawei->postApi('https://api.example.com', ['key' => 'value'], ['Content-Type: application/json'], false, 'GET');
     */
    public function postApi(string $url, $data, array $headers = [], bool $returnHeader = false, string $method = 'POST'): array {
        $curl = curl_init();
        
        // 处理GET请求时的数据
        if (strtoupper($method) === 'GET' && !empty($data) && is_array($data)) {
            $queryString = http_build_query($data);
            $url = strpos($url, '?') === false ? $url . '?' . $queryString : $url . '&' . $queryString;
            $data = ''; // GET请求不需要POSTFIELDS
        }

        // 通用curl配置
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => $returnHeader,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            // 自动处理数据格式：若为数组且Content-Type为json则序列化
            CURLOPT_POSTFIELDS => (strtoupper($method) === 'POST' && !empty($data)) ? (is_array($data) ? json_encode($data, 256 + 64) : $data) : null,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // 错误处理与日志记录
        if ($error || $httpCode < 200 || $httpCode >= 300) {
            Log::error('API请求失败', ['url' => $url,'method' => $method,'http_code' => $httpCode,'error' => $error,'data' => $data]);
            return ['content' => '', 'code' => $httpCode, 'error' => $error ?: 'HTTP状态码异常'];
        }
        return ['content' => $response, 'code' => $httpCode, 'error' => ''];
    }

    /**
     * 获取带缓存的华为云认证Token（优先从Redis读取）
     * @return string 认证Token（获取失败返回空字符串）
     * @description 流程说明：
     *               1. 检查Redis缓存是否存在Token
     *               2. 缓存存在则直接返回
     *               3. 缓存不存在时调用IAM服务获取新Token
     *               4. 成功获取后将Token存入Redis（12小时有效期）
     */
    public function getSingToken() {
        $cachedToken = $this->redis->get($this->cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        $url = $this->url;
        // 调用公共postApi方法替代原curl代码
        $response = self::postApi(
                        $this->iam_url,
                        [
                            'auth' => [
                                'identity' => [
                                    'methods' => ['hw_ak_sk'],
                                    'hw_ak_sk' => [
                                        'access' => ['key' => $this->access_key],
                                        'secret' => ['key' => $this->secret_key]
                                    ]
                                ],
                                'scope' => ['project' => ['name' => $this->region]]
                            ]
                        ],
                        ['Content-Type: application/json;charset=UTF-8'],
                        true // 需要获取响应头
        );

        if ($response['code'] === 201 && empty($response['error'])) {
            preg_match('/X-Subject-Token: (.*?)\\n/', $response['content'], $matches);
            $token = $matches[1] ?? '';
            if ($token) {
                $this->redis->setex($this->cacheKey, $this->cacheExpire, $token);
                return $token;
            }
        }

        return '';
    }

    /**
     * 向华为云图片搜索服务添加图片数据
     * @param string $item_id 产品唯一标识（必填）
     * @param string $desc 图片描述（必填，格式：无域名的图片路径，如 `/image/no_image.png`）
     * @param string $image_url 图片源地址（必填，完整URL，如 `https://example.com/image.png`）
     * @return array 接口响应结果（包含content、code、error字段）
     * @throws string 参数缺失时返回错误提示
     */
    public function runPostImg($item_id = "", $desc = "", $image_url = "", $image_model = "", $image_id = "", $image_price = "", $image_status = "") {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }

        $post_data = [];

        if (empty($item_id)) {
            return '产品ID不能为空';
        } else {
            $post_data["item_id"] = $item_id;
        }
        if (empty($desc)) {
            return '描述不能为空';
        } else {
            $post_data["desc"] = $desc;
        }
        if (empty($image_url)) {
            return '图片源地址不能为空';
        } else {
            $post_data["image_url"] = $image_url;
        }

        if ($this->force) {
            $post_data["force"] = $this->force;
        }
        if (empty($image_model) && empty($image_id)) {
            
        } else {
//            $post_data["custom_tags"]["price"] = $image_price;
            $post_data["custom_tags"]["d_price"] = $image_price;
            $post_data["custom_tags"]["d_model"] = $image_model;
            $post_data["custom_tags"]["d_id"] = $image_id;
            $post_data["custom_tags"]["d_status"] = $image_status;
        }
        $url = $this->url;
        $project_id = $this->project_id;
        $service_name = $this->service_name;

        Log::info("HuaWei_Img::runPostImg", ["$url/v2/$project_id/mms/$service_name/search" => $post_data]);

        // 调用公共postApi方法上传图片数据
        $response = self::postApi(
                        "$url/v2/$project_id/mms/$service_name/data/add",
//            ['item_id' => '123456789999', 'desc' => '/image/no_image.png', 'image_url' => 'https://pub-sgp.obs.ap-southeast-3.myhuaweicloud.com/image/no_image.png'],
//                        ['item_id' => $item_id, 'desc' => $desc, 'image_url' => $image_url,["custom_tags"=>["type"=>$image_type,"id"=>$image_id]] ],
                        $post_data,
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );

        return $response;
    }

    /**
     * 是否强制添加数据，默认为false。
      false: 数据已存在则不进行添加。
      true: 数据已存在仍然覆盖添加。
     * @param type $force false|true
     * @return type
     */
    public function setForce($force = false) {
        $this->force = $force;
        return $this->force;
    }

    /**
     * 基于图片的相似性搜索（用图搜图）
     * @param string $image_url 图片URL地址（与$image_base64二选一会自动适配）
     * @return array 搜索结果响应（包含content、code、error字段）
     * @throws string 数据缺失时返回错误提示
     */
    public function runSearchImg($image_url = "") {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }

        $url = $this->url;
        $project_id = $this->project_id;
        $service_name = $this->service_name;

//        $post_data = ['search_type' => 'CATEGORY', "min_score" => "0.5"];
        $post_data = ['search_type' => 'CATEGORY', "limit" => 15, "min_score" => "0"];
        if (empty($image_url)) {
            return '数据不能为空';
        }
        $image_base64 = self::convertImageToBase64($image_url);
        if (!empty($image_base64["base64"])) {
            $post_data["image_base64"] = $image_base64["base64"];
        }

        Log::info("HuaWei_Img::runSearchImg", ["$url/v2/$project_id/mms/$service_name/search" => ""]);

        // 调用公共postApi方法上传图片数据
        $response = self::postApi(
                        "$url/v2/$project_id/mms/$service_name/search",
//                        ['search_type' => 'CATEGORY', 'image_url' => 'https://pub-sgp.obs.ap-southeast-3.myhuaweicloud.com/image/no_image.png'],
                        $post_data,
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );

        Log::info("HuaWei_Img::runSearchImg::return", ["$url/v2/$project_id/mms/$service_name/search" => $response]);

        return json_decode($response["content"], true);
    }

    /**
     * DNS地址预热加速
     * @param type $image_url ["https://www.bxxx/1.txt", "http://www.bxxx/2.txt"]
     * @return string
     */
    public function runPostImgDNS($image_url = []) {
        $token = $this->getSingToken();
        if (empty($image_url)) {
            return message('图片源地址不能为空', false);
        } else {
            //$post_data["image_url"] = $image_url;
        }
        $refresh = self::CdnCreateRefreshTasks($image_url);//刷新清空
        usleep(30000); // 10 * 1000 = 10,000 微秒
        $preheating = self::CdnCreatePreheatingTasks($image_url);//创建预热缓存任务
        return $preheating;
    }

    /**
     * 检查华为云图片搜索服务中的数据状态
     * @return array 数据检查结果响应（包含content、code、error字段）
     */
    public function runCheckData($item_id = "") {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }
        if (empty($item_id)) {
            return '数据不能为空';
        }
        $url = $this->url;
        $project_id = $this->project_id;
        $service_name = $this->service_name;
        // 调用公共postApi方法上传图片数据
        $response = self::postApi(
                        "$url/v2/$project_id/mms/$service_name/data/check",
                        ['item_id' => $item_id],
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );
        return json_decode($response["content"], true);
    }

    /**
     * 删除数据
     * @return array 数据检查结果响应（包含content、code、error字段）
     */
    public function RunDeleteData($item_id = "") {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }
        if (empty($item_id)) {
            return '数据不能为空';
        }
        $url = $this->url;
        $project_id = $this->project_id;
        $service_name = $this->service_name;
        // 调用公共postApi方法上传图片数据
        $response = self::postApi(
                        "$url/v2/$project_id/mms/$service_name/data/delete",
//                        ["force"=>true,'item_id' => $item_id],
                        ['item_id' => $item_id],
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );
        return json_decode($response["content"], true);
    }

    /**
     * 更新华为云图片搜索服务中的图片数据
     * @param string $item_id 产品唯一标识（必填）
     * @param string $desc 图片描述（必填，格式：无域名的图片路径，如 `/image/no_image.png`）
     * @param string $image_url 图片源地址（必填，完整URL，如 `https://example.com/image.png`）
     * @return array 接口响应结果（包含content、code、error字段）
     * @throws string 参数缺失时返回错误提示
     */
    public function runUpdateData($item_id = "", $desc = "", $image_url = "") {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }

        if (empty($item_id)) {
            return '产品ID不能为空';
        }
        if (empty($desc)) {
            return '描述不能为空';
        }


        if (!empty($item_id)) {
            $post_data["item_id"] = $item_id;
        }
        if (!empty($desc)) {
            $post_data["desc"] = $desc;
        }
        if (!empty($image_url)) {
            $post_data["image_url"] = $image_url;
        }

        $url = $this->url;
        $project_id = $this->project_id;
        $service_name = $this->service_name;
        // 调用公共postApi方法上传图片数据
        $response = self::postApi(
                        "$url/v2/$project_id/mms/$service_name/data/update",
                        $post_data,
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );
        return json_decode($response["content"], true);
    }

    /**
     * 将本地图片文件转换为Base64编码
     * @param string $file 本地图片文件路径（必填）
     * @return array 转换结果：
     *               - img: 原始文件路径
     *               - base64: 图片Base64编码字符串（转换失败返回空数组）
     * @throws string 文件为空时返回错误提示
     */
    public function image_base64($file = "") {

        if (empty($file)) {
            return '文件不能为空';
        }
        //$file：图片地址
        //Filetype: JPEG,PNG,GIF 
//        $file = "encode.jpg";
        if ($fp = fopen($file, "rb", 0)) {
            $gambar = fread($fp, filesize($file));
            fclose($fp);
            $base64 = chunk_split(base64_encode($gambar));
            // 输出
            $encode = '<img src="data:image/jpg/png/gif;base64,' . $base64 . '" >';
            echo $encode;
            return ["img" => $file, "base64" => $base64];
        }
        return [];
    }

    /**
     * 自动转换远程/本地图片为Base64编码（核心新增）
     * @param string $source 图片源（本地文件路径或远程URL）
     * @return array 转换结果：
     *               - type: 图片MIME类型（如 image/jpeg）
     *               - base64: 图片Base64编码字符串
     *               - error: 错误信息（成功时为空）
     * @throws string 源为空时返回错误提示
     */
    public function convertImageToBase64(string $source): array {
        if (empty($source)) {
            return ['type' => '', 'base64' => '', 'error' => '图片源不能为空'];
        }
        $imageContent = '';
        $mimeType = '';

        // 判断是否为远程URL
        if (strpos($source, 'http://') === 0 || strpos($source, 'https://') === 0) {
            // 处理远程图片
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $source,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HEADER => false
            ]);
            $imageContent = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($httpCode !== 200 || $error) {
                Log::error('远程图片下载失败', ['source' => $source, 'http_code' => $httpCode, 'error' => $error]);
                return ['type' => '', 'base64' => '', 'error' => '远程图片下载失败：' . ($error ?: 'HTTP状态码异常')];
            }

            // 自动检测MIME类型
            $mimeType = mime_content_type('data://application/octet-stream;base64,' . base64_encode($imageContent));
        } else {
            // 处理本地图片
            if (!file_exists($source)) {
                return ['type' => '', 'base64' => '', 'error' => '本地文件不存在'];
            }
            if (!is_readable($source)) {
                return ['type' => '', 'base64' => '', 'error' => '本地文件不可读'];
            }

            $imageContent = file_get_contents($source);
            $mimeType = mime_content_type($source);
        }

        if (empty($imageContent)) {
            return ['type' => '', 'base64' => '', 'error' => '图片内容为空'];
        }

        $base64 = chunk_split(base64_encode($imageContent));
        return ['type' => $mimeType, 'base64' => $base64, 'error' => ''];
    }

    ####DNS相关接口

    /**
     * #创建预热缓存任务   v1.0/cdn/content/preheating-tasks
     * @param type $preheating_task ["https://www.bxxx/1.txt", "http://www.bxxx/2.txt"]
     * @return string
     */
    public function CdnCreatePreheatingTasks($preheating_task = []) {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }
        $url = $this->url;  ## https://cdn.myhuaweicloud.com
       
        // 调用公共postApi方法上传图片数据
        $response = self::postApi(
                        "$url/v1.0/cdn/content/preheating-tasks",
//                        ["force"=>true,'item_id' => $item_id],
                        ['preheating_task' => ["zh_url_encode"=>true,"urls" => $preheating_task]],
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );
        return json_decode($response["content"], true);
    }

    /**
     * CreatePreheatingTasks
     * 创建预热缓存任务
     * @param type $preheating_task ["https://www.bxxx/1.txt", "http://www.bxxx/2.txt"]
     * @return string
     */
    public function CdnCreateRefreshTasks($preheating_task = []) {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }
        if (empty($item_id)) {
            return '数据不能为空';
        }
        $url = $this->url;
        // 调用公共postApi方法上传图片数据   https://cdn.myhuaweicloud.com/v1.0/cdn/content/refresh-tasks
        $response = self::postApi(
                        "$url/v1.0/cdn/content/refresh-tasks",
//                        ["force"=>true,'item_id' => $item_id],
                        ['refresh_task' => ["type"=>"file","zh_url_encode"=>true,"urls" => $preheating_task]],
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
        );
        return json_decode($response["content"], true);
    }
    
    
    
    /**
     * ShowQuota
     * 查询用户配额
     * @param type $preheating_task ["https://www.bxxx/1.txt", "http://www.bxxx/2.txt"]
     * @return string
     */
    public function CdnShowQuota() {
        $token = $this->getSingToken();
        if (!$token) {
            return 'Token获取失败';
        }
        
        $url = $this->url;
        // 调用公共postApi方法上传图片数据   https://cdn.myhuaweicloud.com/v1.0/cdn/quota
        $response = self::postApi(
                        "$url/v1.0/cdn/quota",
                        ["force"=>true],
//                        ['refresh_task' => ["type"=>"file","zh_url_encode"=>true,"urls" => $preheating_task]],
//                        [],
                        ['X-Auth-Token: ' . $token, 'Content-Type: application/json']
                ,0,"GET"
        );
        return json_decode($response["content"], true);
    }

}

    
