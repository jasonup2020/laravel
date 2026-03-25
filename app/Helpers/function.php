<?php

// +----------------------------------------------------------------------
// | Laravel 10前后端分离旗舰版框架
// +----------------------------------------------------------------------
// | 版权所有 CMF研发中心
// +----------------------------------------------------------------------
// | 作者: Jason jasonps2020@gmail.com
// +----------------------------------------------------------------------
// | 免责声明:
// | 本软件框架禁止任何单位和个人用于任何违法、侵害他人合法利益等恶意的行为，禁止用于任何违
// | 反我国法律法规的一切平台研发，任何单位和个人使用本软件框架用于产品研发而产生的任何意外
// | 、疏忽、合约毁坏、诽谤、版权或知识产权侵犯及其造成的损失 (包括但不限于直接、间接、附带
// | 或衍生的损失等)，本团队不承担任何法律责任。本软件框架只能用于公司和个人内部的法律所允
// | 许的合法合规的软件产品研发，详细声明内容请阅读《框架免责声明》附件；
// +----------------------------------------------------------------------
// 为方便系统核心模块升级，二次开发中需要用到的公共函数请写在此文件中，不允许修改系统核心公共函数common.php文件

if (!function_exists('get_list_where')) {

    /**
     * 返回多参数信息
     * @author
     * @author $map 返回传入参数
     * @author $map_where 返回传入Where
     * @author $map_wherein  返回传入Where
     * @author $is_sql 返回是否输出SQL语句
     * @author $sort 排序
     * @author $request Get或者Post数据
     * @since 2022-7-26
     * get_list_where
     * $map,$map_where,$map_wherein,$is_sql,$sort,$request
     * [$map,$map_where,$map_where,$is_sql,sort]
     * ['map'=> $map,'where'=> $map_where,'wherein'=> $map_where,'is_sql'   => $is_sql,'sort'  => $sort,]
     *
     * @return array list($map,$map_where,$map_wherein,$is_sql,$sort,$request) =get_list_where();
     * @return array list($map,$map_where,$map_wherein,$is_sql,$sort,$request) =get_list_where(@func_get_args()[0]);
     * @return array list($map,$map_where,$map_wherein,$is_sql,$sort,$request) =get_list_where(["where"=>$where,"wherein"=>$wherein]);
     */
    function get_list_where() {
        // 初始化变量
        $map = [];
        $sort = [['id', 'desc']];
        $is_sql = 0;
        // 获取参数
        $map_where = $map_wherein = [];
        $argList = func_get_args();
        $request = Request();
        if (!empty($argList)) {
            // 查询条件
            $map = (isset($argList[0]) && !empty($argList[0])) ? $argList[0] : [];
            if (!empty($map["where"]) || !empty($map["wherein"])) {
                $map_where = !empty($map["where"]) ? $map["where"] : [];
                $map_wherein = !empty($map["wherein"]) ? $map["wherein"] : [];
                $map = [];
                $map = $map_where;
            }
            // 排序
            $sort = (isset($argList[1]) && !empty($argList[1])) ? $argList[1] : ['id', 'desc'];
            // 是否打印SQL
            $is_sql = isset($argList[2]) ? isset($argList[2]) : 0;
        }

        // 打印SQL
        if ($is_sql) {
            $this->model->getLastSql(1);
        }
        return [$map
            , $map_where
            , $map_wherein
            , $is_sql
            , $sort
            , $request->all()
//            ,'argList'=>$argList
        ];
    }

}

if (!function_exists('get_random_code2')) {

    /**
     * 获取指定位数的随机码 不带[I,l,O,0,o]
     * @param int $num 随机码长度
     * @return string 返回字符串
     * @author jason
     * @date 2023-03-20
     */
    function get_random_code2($num = 12) {
        $codeSeeds = "ABCDEFGHJKLMNPQRSTUVWXYZ";
        $codeSeeds .= "abcdefghijkmnpqrstuvwxyz";
        $codeSeeds .= "0123456789";
        $len = strlen($codeSeeds);
        $code = "";
        for ($i = 0; $i < $num; $i++) {
            $rand = rand(0, $len - 1);
            $code .= $codeSeeds[$rand];
        }
        return $code;
    }

}





if (!function_exists('saveNPoint')) {
    /**
     * 保留小数点后{$point}位
     *
     * @param float|int|string $number
     * @param int $point 小数点后几位
     * @return mixed
     * @author      Jewy <554696608@qq.com>
     * @datatime    2023/3/17 18:01
     */
    function saveNPoint($number, int $point = 3)
    {
        $format = '%.'.$point.'f';
        return $number !== '' ? sprintf($format, $number) : 0;
    }

}




if (!function_exists('randomString')) {
    /**
     * 获取随机字符串
     * @param  integer $length  字符串长度
     * @param  boolean $isupper 是否包含大写字母
     * @param  boolean $islower 是否包含小写字母
     * @param  boolean $isspec  是否包含特殊字符
     * @return string
     */
    function randomString($length = 6, $islower = true, $isupper = false, $isspec = false, $isnumber = true, $other = '')
    {
        $numbers     = "0,1,2,3,4,5,6,7,8,9";
        $lowerLetter = "a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z";
        $upperLetter = "A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z";
        $specialChars = "!,@,#,$,?,|,{,/,:,;,%,^,&,*,(,),-,_,[,],},<,>,~,+,=";
        !$isnumber   && $numbers = '';
        $isupper     && $numbers .= $upperLetter;
        $islower     && $numbers .= $lowerLetter;
        $isspec      && $numbers .= $specialChars;

        if ($other) {
            $numbers .= is_array($other) ? implode(',', $other) : $other;
        }
        $numbers     = explode(',', $numbers);

        shuffle($numbers); //打乱数组顺序
        $length      = $length > count($numbers) ? count($numbers) : $length;
        $string      = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $numbers[mt_rand(0, count($numbers) - 1)]; //随机取出一位
        }
        return $string;
    }
}


if (!function_exists('salts')) {
    /**
     * 随机字符 或 密码salt
     *
     * @param mixed $request
     * @return mixed
     * @datatime    2024/9/27 0027 9:43
     */
    function salts($length = 32) {
        // Create random token
        $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        $max = strlen($string) - 1;

        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $string[mt_rand(0, $max)];
        }

        return $token;
    }
}

if (!function_exists('numForWord')) {
    /**
     * 号码转单词
     *
     * @param mixed $request
     * @return mixed
     * @datatime    2024/9/27 0027 9:43
     */
    function numForWord($num, $max = 'Z') {
        // Create random token
        $string = "A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z";

        $arr = explode(',', $string);
        if ($num > 1) {
            return $arr[$num - 1] ?? $max;
        } else {
            return 'A';
        }
    }
}

if (!function_exists('numToChinese')) {
    /**
     * 数字转汉字
     *
     * @param mixed $request
     * @return mixed
     * @datatime    2024/9/27 0027 9:43
     */

    function numToChinese($num) {
        $cn = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        $result = '';
        $arr = str_split($num);
        foreach ($arr as $n) {
            $result .= $cn[$n] ?? '';
        }
        return $result;
    }
}


if (!function_exists('numToChineseMonth')) {
    /**
     * 数字转汉字月份
     *
     * @param mixed $request
     * @return mixed
     * @datatime    2024/9/27 0027 9:43
     */
    function numToChineseMonth($num) {
        $months = [
            '一', '二', '三', '四', '五', '六', '七', '八', '九', '十', '十一', '十二'
        ];
        if ($num >= 1 && $num <= 12) {
            return $months[$num - 1];
        } else {
            return '';
        }
    }
}


if (!function_exists('numToChineseWeek')) {
    /**
     * 数字转汉字星期n
     *
     * @param mixed $request
     * @return mixed
     * @datatime    2024/9/27 0027 9:43
     */
    function numToChineseWeek($num) {
        $months = [
            '日', '一', '二', '三', '四', '五', '六'
        ];
        if ($num >= 0 && $num <= 6) {
            return $months[$num];
        } else {
            return '';
        }
    }
}


if (!function_exists('getMsecTime')) {
    /**
     * 获取毫秒级别的时间戳
     */
    function getMsecTime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
}


if (!function_exists('randomDate')) {
    /**
     * 生成某个范围内的随机时间
     * @param string $begin
     * @param string $end
     * @param boolean $now 是否是时间戳 格式为 Boolean
     * @return int|string
     */
    function randomDate($begin = "", $end = "", $now = true)
    {
        $timestamp = rand(strtotime($begin ?: date("Y-m-d", time())), strtotime($end ?: date("Y-m-d 23:59:59", time())));
        return $now ? date("Y-m-d H:i:s", $timestamp) : $timestamp;
    }
}


//if (!function_exists('message')) {
//
//    /**
//     * 消息数组
//     * @param string $msg 提示文字
//     * @param bool $success 是否成功true或false
//     * @param array $data 结果数据
//     * @param int $code 编码
//     * @return array 返回结果
//     * @author 艾乐
//     * @date 2023-03-20
//     */
//    function message($msg = "操作成功", $success = true, $data = [], $code = 200) {
//
//        [
////            以下是Laravel的常见错误码和其定义：
////    1xx 错误码：信息类错误
//
//            100 => ["en" => "Continue", "cn" => "服务器已经接收到了请求并进行了一些处理，但仍需要客户端发送剩余的请求。这个状态码是用于HTTP/1.1协议。"],
//            101 => ["en" => "Switching Protocols", "cn" => "表示客户端希望服务器升级协议，例如从HTTP/1.0升级到HTTP/1.1。"],
////    2xx 错误码：成功类错误
//            200 => ["en" => "OK：表示请求已经成功处理。"],
//            201 => ["en" => "Created", "cn" => "表示请求已经被成功处理，并且服务器创建了一些资源。"],
//            202 => ["en" => "Accepted", "cn" => "表示请求已经被接受，但尚未被服务器处理。"],
//            203 => ["en" => "Non-Authoritative Information", "cn" => "表示服务器已经成功处理了请求，但返回的实体包含了一些不是来自原始服务器的信息。"],
//            204 => ["en" => "No Content", "cn" => "表示服务器已经成功处理请求，但没有返回任何内容。"],
//            205 => ["en" => "Reset Content", "cn" => "表示服务器已经成功处理请求，但要求客户端重置视图。"],
//            206 => ["en" => "Partial Content", "cn" => "表示服务器已经成功处理了部分请求，并返回了部分内容。"],
////    3xx 错误码：重定向类错误
//            300 => ["en" => "Multiple Choices", "cn" => "表示请求返回的实体可以由多个位置来访问。"],
//            301 => ["en" => "Moved Permanently", "cn" => "表示资源已经被永久移动到了新的位置。"],
//            302 => ["en" => "Found", "cn" => "表示资源已经被暂时移动到了新的位置。"],
//            303 => ["en" => "See Other", "cn" => "表示请求返回的实体可以在另一个URI中获得。"],
//            304 => ["en" => "Not Modified", "cn" => "表示资源没有被修改过，可以直接从缓存中获取。"],
//            307 => ["en" => "Temporary Redirect", "cn" => "与302类似，但使用POST请求的客户端不应该更改请求方法。"],
////    4xx 错误码：客户端错误类错误
//            400 => ["en" => "Bad Request", "cn" => "表示客户端发送的请求无效。"],
//            401 => ["en" => "Unauthorized", "cn" => "表示客户端需要进行身份验证才能访问资源。"],
//            402 => ["en" => "Payment Required", "cn" => "表示请求的资源需要付费。"],
//            403 => ["en" => "Forbidden", "cn" => "表示客户端没有权限访问请求的资源。"],
//            404 => ["en" => "Not Found", "cn" => "表示请求的资源不存在。"],
//            405 => ["en" => "Method Not Allowed", "cn" => "表示客户端使用了不被允许的HTTP方法访问请求的资源。"],
//            406 => ["en" => "Not Acceptable", "cn" => "表示请求的内容类型与服务器无法处理的内容类型不匹配。"],
//            407 => ["en" => "Proxy Authentication Required", "cn" => "表示客户端不具有访问请求资源所需的代理身份验证信息。"],
//            408 => ["en" => "Request Timeout", "cn" => "表示请求超时。"],
//            409 => ["en" => "Conflict", "cn" => "表示请求与资源的当前状态冲突。"],
//            410 => ["en" => "Gone", "cn" => "表示请求资源不可用，通常是因为已经被永久删除。"],
//            411 => ["en" => "Length Required", "cn" => "表示缺少必需的Content-Length头。"],
//            412 => ["en" => "Precondition Failed", "cn" => "表示请求头中给出的一些先决条件失败了。"],
//            413 => ["en" => "Payload Too Large", "cn" => "表示请求的实体过大。"],
//            414 => ["en" => "URI Too Long", "cn" => "表示请求的URI过长。"],
//            415 => ["en" => "Unsupported Media Type", "cn" => "表示请求的实体类型不受支持。"],
//            416 => ["en" => "Range Not Satisfiable", "cn" => "表示请求的范围无法满足。"],
//            417 => ["en" => "Expectation Failed", "cn" => "表示请求无法满足服务器中的Expect请求头字段。"],
////    5xx 错误码：服务器错误类错误
//            500 => ["en" => "Internal Server Error", "cn" => "表示服务器遇到了错误，无法完成请求。"],
//            501 => ["en" => "Not Implemented", "cn" => "表示服务器不支持客户端请求的功能。"],
//            502 => ["en" => "Bad Gateway", "cn" => "表示服务器作为网关或代理时，接收到了错误的响应。"],
//            503 => ["en" => "Service Unavailable", "cn" => "表示服务器当前无法处理请求，可能是由于维护或过载。"],
//            504 => ["en" => "Gateway Timeout", "cn" => "表示服务器作为网关或代理时，未及时接收到来自上游服务器的响应。"],
//            505 => ["en" => "HTTP Version Not Supported", "cn" => "表示客户端使用的HTTP协议版本不被服务器支持。"]
//        ];
//
//        if ($msg == "操作成功") {
//            $msg = __("public.MESSAGE_OK");
//        }
//        $result = ['success' => $success, 'msg' => $msg, 'data' => $data];
//        if (is_array($code)) {
//            foreach ($code as $codeKey => $codeValue) {
//                if (!empty($codeKey)) {
//                    $result[$codeKey] = $codeValue;
//                }
//            }
//            if ($success) {
//                $result['code'] = 200;
//            } else {
//                $result['code'] = 501;
//            }
//        } else {
//            if ($success) {
//                $result['code'] = $code ? $code : 200;
//            } else {
//                $result['code'] = $code ? $code : 501;
//            }
//        }
//        return $result;
//    }
//
//}


if (!function_exists('has_resource')) {

    /**
     * 数据脱敏 
     * has_resource($u_u_data,"UserUserResource") app\Http\Resources\UserUserResource.php 
     * @param int $num 随机码长度
     * @return string 返回字符串
     * @author jason
     * @date 2023-03-20
     */
    function has_resource($data, $name = '', $type = 0) {
        $resourcePath = 'app\Http\Resources\\';
        $resourceNameSpacePath = 'App\Http\Resources\\';
        $nameSpace = $resourceNameSpacePath . $name;
        if (is_object($data)) {
            $data = $data->toArray();
        }
        $ret_ret = new $nameSpace($data);
        $ret_ret2 = $ret_ret->resource ?? [];
        return $ret_ret2;
    }

    ;
}


if (!function_exists('curl_request')) {

    /**
     * curl请求(支持get和post)
     * @param string $url 请求地址
     * @param array $data 请求参数
     * @param string $type 请求类型(默认：post)
     * @param bool $https 是否https请求true或false
     * @return bool|string 返回请求结果
     * @param type $httpheader ["Authorization: Bearer *** ","Content-Type: application/**"]
     * @return boolean
     * @author jason
     * @date 2023-03-20
     */
    function curl_request($url, $data = [], $type = 'post', $https = false,$httpheader=[]) {
        
        print_r(["httpheader"=>$httpheader]);
        // 初始化
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        // 设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 是否要求返回数据
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if(!empty($httpheader)){
            //设置Authorization,Content-Type, *** 等其它数据 
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        }
        if ($https) {
            // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // 从证书中检查SSL加密算法是否存在
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (strtolower($type) == 'post') {
            // 设置post方式提交
            curl_setopt($ch, CURLOPT_POST, true);
            // 提交的数据
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } elseif (!empty($data) && is_array($data)) {
            // get网络请求
            $url = $url . '?' . http_build_query($data);
        }
        // 设置抓取的url
        curl_setopt($ch, CURLOPT_URL, $url);
        // 执行命令
        $result = curl_exec($ch);
        if ($result === false) {
            return false;
        }
        // 关闭URL请求(释放句柄)
        curl_close($ch);
        return $result;
    }

}


 
if (!function_exists('message')) {

    /**
     * 消息数组
     * @param string $msg 提示文字
     * @param bool $success 是否成功true或false
     * @param array $data 结果数据
     * @param int $code 编码
     * @return array 返回结果
     * @author 艾乐
     * @date 2023-03-20
     */
    function message($msg = "操作成功", $success = true, $data = [], $code = 0) {
        if ($msg == "操作成功" || $msg == "ok") {
            $msg = __("public.MESSAGE_OK");
        }
        $result = ['success' => $success, 'msg' => $msg, 'data' => $data];
        if (is_array($code)) {
            foreach ($code as $codeKey => $codeValue) {
                if (!empty($codeKey)) {
                    $result[$codeKey] = $codeValue;
                }
            }
            if ($success) {
                $result['code'] = 0;
            } else {
                $result['code'] = -1;
            }
        } else {
            if ($success) {
                $result['code'] = $code ? $code : 0;
            } else {
                $result['code'] = $code ? $code : -1;
            }
        }
        return $result;
    }

}

 


if (!function_exists('is_validator')) {

    /**
     * 前置验证 Validator
     * @param Request $make_data Request|GET|POST [key=>value]
     * @param type $is_where [key=>required|integer|min:0|max:9999]
     * @param type $message [key=>required || key.mix=>"required最小不能是什么"]
     * @return array|NULL 返回数组或 者  空(正常)
     * @author jason
     * @date 2023-03-20
     */
    function is_validator($make_data = [], $is_where = [], $message = []) {
        if (empty($make_data) && is_array($make_data) && is_array($is_where) && empty($make_data)) {
            return " Get OR Post is not data " . json_encode(array_keys($is_where));
        }
        $make_rules = $is_where;

        $make_rules2 = $message ?? [];
        $make_messages = [
            'required' => " :attribute 为必填项 ",
            'min' => " :attribute 不能少于 :min",
            'max' => " :attribute 不能超过  :max",
            'in' => " :in  :attribute ",
            'not_in' => ":attribute 不能是 :not_in  ",
            'ip' => " :attribute IP 格式错误",
            'ipv4' => " :attribute IPV4地址错误",
            'ipv6' => " :attribute IPV6地址错误",
            'integer' => " :attribute 必项整数",
            'after_or_equal' => " :attribute 大于等于",
            'alpha_dash' => " :attribute 验证字段必须全是字母和数字",
        ];

        $validator = Validator::make($make_data, $make_rules, $make_messages, $make_rules2);
        if ($validator->fails()) {
            $errors_message = $validator->errors()->getMessages();
            $error_msg = "";
            foreach ($errors_message as $key => $value) {
                if (!empty($error_msg) && !empty($value)) {
                    $error_msg .= " , ";
                }
//                $error_msg .= $key.":". $value[0];
                $error_msg .= $key . ":" . $value[0]??"";
            }
            return " " . $error_msg;
//            return ['success' => false, 'msg' => $error_msg, 'data' =>[]];
        }
    }

    
}


if (!function_exists('permission_decomposition')) {

        /**
         * * 质数权限拆分  
         * 例如 9  14  分解成  2 4 8 
         * @param type $n [1,2,4,8,16,32,64,128,256,512,1024,2048] 4095 
         * @return array
         */
        function permission_decomposition($n) {
            $n |= 0;
            $pad = 0;
            $arr = array();
            while ($n) {
                if ($n & 1)
                    array_push($arr, 1 << $pad);
                $pad++;
                $n >>= 1;
            }
            return $arr;
        }

    }






use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
if(!function_exists('db_temp_remote')){

     /**
     * 临时远程数据库操作方法
     * 支持增删改查操作以及原生SQL执行
     * 
     * @param int $site_id 站点ID，用于区分不同的临时连接
     * @param array $db_server 数据库服务器配置数组
     *                         - ip: 数据库服务器IP地址
     *                         - db_port: 数据库端口（可选，默认3306）
     *                         - database: 数据库名称（可选）
     *                         - db_username: 数据库用户名
     *                         - db_pwd: 数据库密码
     * @param array $sql SQL操作配置数组
     *                   - table: 表名（用于预定义操作）
     *                   - where: 查询条件，格式为[[列名, 操作符, 值]]
     *                           支持普通操作符(=, >, <, >=, <=, !=等)
     *                           支持in操作符: [列名, 'in', [值1, 值2, ...]]
     *                           支持or操作符: [列名, 'or', 子操作符, 值] 或 [列名, 'or', 'in', [值1, 值2, ...]]
     *                   - handle: 操作类型或原生SQL语句
     *                             预定义操作: select/find, first, insert, update, delete, count
     *                             原生SQL: 当handle不是预定义操作时，将其视为SQL语句执行
     *                   - data: 用于insert/update操作的数据数组
     * @return mixed 查询结果或操作影响行数
     * @throws \Exception 当操作失败时抛出异常
     * 
     * 测试用例：
     * 
     * 1. SELECT查询示例:
     * ```php
     * $site_id = 1;
     * $db_server = [
     *     'ip' => '127.0.0.1',
     *     'db_username' => 'root',
     *     'db_pwd' => 'password',
     *     'database' => 'test_db'
     * ];
     * $sql = [
     *     'table' => 'users',
     *     'where' => [
     *         ['status', '=', 1],
     *         ['created_at', '>', '2024-01-01']
     *     ],
     *     'handle' => 'select'
     * ];
     * $result = db_temp_remote($site_id, $db_server, $sql);
     * ```
     * 
     * 2. 使用IN操作符查询:
     * ```php
     * $sql = [
     *     'table' => 'products',
     *     'where' => [
     *         ['category_id', 'in', [1, 2, 3]],
     *         ['price', '<', 100]
     *     ],
     *     'handle' => 'select'
     * ];
     * ```
     * 
     * 3. 使用OR操作符查询:
     * ```php
     * $sql = [
     *     'table' => 'orders',
     *     'where' => [
     *         ['customer_id', '=', 123],
     *         ['status', 'or', '=', 'completed'],
     *         ['status', 'or', '=', 'processing']
     *     ],
     *     'handle' => 'select'
     * ];
     * ```
     * 
     * 4. 插入数据示例:
     * ```php
     * $sql = [
     *     'table' => 'customers',
     *     'handle' => 'insert',
     *     'data' => [
     *         'name' => 'John Doe',
     *         'email' => 'john@example.com',
     *         'created_at' => now()
     *     ]
     * ];
     * ```
     * 
     * 5. 更新数据示例:
     * ```php
     * $sql = [
     *     'table' => 'products',
     *     'where' => [['id', '=', 100]],
     *     'handle' => 'update',
     *     'data' => ['price' => 99.99, 'updated_at' => now()]
     * ];
     * ```
     * 
     * 6. 删除数据示例:
     * ```php
     * $sql = [
     *     'table' => 'temp_records',
     *     'where' => [['created_at', '<', '2024-01-01']],
     *     'handle' => 'delete'
     * ];
     * ```
     * 
     * 7. COUNT统计示例:
     * ```php
     * $sql = [
     *     'table' => 'orders',
     *     'where' => [['status', '=', 'pending']],
     *     'handle' => 'count'
     * ];
     * ```
     * 
     * 8. 原生SQL查询示例:
     * ```php
     * $sql = [
     *     'handle' => 'SELECT u.name, o.order_number FROM users u JOIN orders o ON u.id = o.user_id WHERE o.total > ?',
     *     'where' => [[null, null, 100]] // 参数绑定值
     * ];
     * ```
     * 
     * 9. 复杂原生SQL带多参数:
     * ```php
     * $sql = [
     *     'handle' => 'SELECT * FROM products WHERE category_id = ? AND (price BETWEEN ? AND ?) AND status = ?',
     *     'where' => [
     *         [null, null, 5],    // 第一个参数: category_id
     *         [null, null, 50],   // 第二个参数: 最小价格
     *         [null, null, 200],  // 第三个参数: 最大价格
     *         [null, null, 1]     // 第四个参数: status
     *     ]
     * ];
     * ```
     */
    function db_temp_remote($site_id=0,$db_server=["ip"=>"","db_username"=>"","db_pwd"=>"","database"=>""],$sql=["table"=>"","where"=>"","handle"=>"","data"=>[]]) {
        // 生成临时连接名称
        $temp_remote = "temp_remote_$site_id";
        $result = null;
        
        try {
            // 动态配置数据库连接参数
            // 根据传入的数据库服务器配置，设置Laravel数据库连接
            Config::set("database.connections.$temp_remote", [
                'driver' => 'mysql',      // 数据库驱动
                'host' => $db_server["ip"],  // 数据库服务器IP
                'port' => $db_server["db_port"] ?? 3306,  // 数据库端口（默认3306）
                'database' => $db_server["database"] ?? 'information_schema', // 数据库名
                'username' => $db_server["db_username"], // 数据库用户名
                'password' => $db_server["db_pwd"], // 数据库密码
                'charset' => 'utf8mb4',   // 字符集
                'collation' => 'utf8mb4_unicode_ci', // 排序规则
            ]);
            
            // 获取数据库连接实例
            $connection = DB::connection($temp_remote);
            
            // 对于预定义操作，需要表名
            // 注意：对于原生SQL操作，表名可以为空
            $needTable = in_array(strtolower($sql["handle"] ?? ''), ['select', 'find', 'first', 'insert', 'update', 'delete', 'count']);
            if ($needTable && empty($sql["table"])) {
                throw new \Exception("Table name is required for this operation");
            }
            
            // 仅在需要表名的操作中创建查询构建器
            $query = null;
            if ($needTable) {
                // 创建查询构建器实例
                $query = $connection->table($sql["table"]);
                
                // 处理where条件数组 - 支持多条件复杂查询
                if (!empty($sql["where"]) && is_array($sql["where"])) {
                    foreach ($sql["where"] as $condition) {
                        if (count($condition) >= 3) {
                            $column = $condition[0]; // 列名
                            $operator = $condition[1]; // 操作符
                            $value = $condition[2]; // 值
                            
                            // 根据操作符类型进行不同处理
                            switch (strtolower($operator)) {
                                case 'in':
                                    // in 操作符处理 - 要求value为数组格式
                                    if (is_array($value)) {
                                        $query->whereIn($column, $value);
                                    }
                                    break;
                                case 'or':
                                    // or 操作符处理 - 支持 [列名, 'or', 子操作符, 值] 格式
                                    if (count($condition) > 3) {
                                        $orOperator = $condition[2]; // or条件中的子操作符
                                        $orValue = $condition[3]; // or条件中的值
                                        
                                        // 支持orWhere和orWhereIn
                                        if (strtolower($orOperator) == 'in' && is_array($orValue)) {
                                            $query->orWhereIn($column, $orValue);
                                        } else {
                                            $query->orWhere($column, $orOperator, $orValue);
                                        }
                                    }
                                    break;
                                default:
                                    // 普通操作符处理（=, >, <, >=, <=, !=等）
                                    $query->where($column, $operator, $value);
                                    break;
                            }
                        }
                    }
                }
            }
            
            // 根据handle执行不同操作 - 支持多种数据库操作类型
            switch (strtolower($sql["handle"])) {
                case 'select':
                case 'find':
                    // 查询多条记录，返回结果集（Collection）
                    $result = $query->get();
                    break;
                case 'first':
                    // 查询单条记录，返回第一条匹配结果（StdClass对象或null）
                    $result = $query->first();
                    break;
                case 'insert':
                    // 插入数据操作 - 验证数据存在且为数组
                    if (!empty($sql["data"]) && is_array($sql["data"])) {
                        $result = $query->insert($sql["data"]); // 返回布尔值表示是否成功
                    } else {
                        throw new \Exception("Data is required for insert operation");
                    }
                    break;
                case 'update':
                    // 更新数据操作 - 验证数据存在且为数组
                    if (!empty($sql["data"]) && is_array($sql["data"])) {
                        $result = $query->update($sql["data"]); // 返回受影响的行数
                    } else {
                        throw new \Exception("Data is required for update operation");
                    }
                    break;
                case 'delete':
                    // 删除数据操作 - 返回删除的行数
                    $result = $query->delete();
                    break;
                case 'count':
                    // 统计记录数 - 返回满足条件的记录数量
                    $result = $query->count();
                    break;
                default:
                    // 原生SQL处理逻辑 - 当handle不是预定义操作时，将其视为SQL语句
                    $sqlStatement = $sql["handle"];
                    $bindings = [];
                    
                    // 从where条件中提取参数绑定值
                    // where数组中的第三个元素作为SQL语句中?的参数值
                    if (!empty($sql["where"]) && is_array($sql["where"])) {
                        foreach ($sql["where"] as $condition) {
                            if (count($condition) >= 3) {
                                $bindings[] = $condition[2]; // 提取参数值用于绑定
                            }
                        }
                    }
                    
                    // 执行原生SQL查询，支持参数绑定防止SQL注入
                    $result = $connection->select($sqlStatement, $bindings);
            }
            
        } catch (\Exception $e) {
            // 记录错误日志，包含数据库服务器信息和错误消息
            // 使用JSON编码确保特殊字符正确转义（JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE）
            Log::error('Error executing query on server ' . json_encode($db_server, 256 + 64) . ': ' . $e->getMessage());
            throw $e; // 重新抛出异常以便调用者可以捕获和处理
        } finally {
            // 确保在操作完成后断开数据库连接
            // 无论操作成功或失败，都会执行此代码块以释放资源
            DB::disconnect($temp_remote);
        }

        return $result; // 返回操作结果（查询结果集、影响行数或布尔值）
    }
}





if(!function_exists('securityFilter')){
    /**
     * 安全验证和过滤方法
     * 对输入进行XSS、SHELL、PHP、MySQL、JS等安全验证
     * 对有问题的内容直接转义成HTML实体
     * 
     * @param string $input 输入内容
     * @return string 安全过滤后的内容
     */
    function securityFilter($input) {
        if (empty($input)) {
            return '';
        }

        // 1. 移除危险字符和标签
        $input = strip_tags($input);

        // 2. 检测和过滤SHELL命令注入
        $shell_patterns = [
            '/\$\s*\(/i', '/`/i', '/\|/i', '/&/i', '/;/i', '/\|\|/i', '/&&/i',
            '/(exec|system|shell_exec|passthru|proc_open|popen|eval|assert|create_function)/i'
        ];
        $input = preg_replace($shell_patterns, '', $input);

        // 3. 检测和过滤PHP代码注入
        $php_patterns = [
            '/<\?php/i', '/<\?=/i', '/<\?/i', '/\?>\s*<\?/i',
            '/(\\$_GET|\\$_POST|\\$_REQUEST|\\$_COOKIE|\\$_SERVER)/i'
        ];
        $input = preg_replace($php_patterns, '', $input);

        // 4. 检测和过滤MySQL注入
        $sql_patterns = [
            '/(union\s+select|select.*from|insert\s+into|update.*set|delete\s+from|drop\s+table|create\s+table)/i',
            '/\'/i', '/\"/i', '/;/i'
        ];
        $input = preg_replace($sql_patterns, '', $input);

        // 5. 检测和过滤JavaScript注入
        $js_patterns = [
            '/<script/i', '/<\/script>/i', '/javascript:/i', '/on\w+\s*=/i',
            '/(alert|confirm|prompt|document\.cookie|window\.location|eval|setTimeout|setInterval)/i'
        ];
        $input = preg_replace($js_patterns, '', $input);

        // 6. 转义HTML特殊字符（XSS防护）
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 7. 移除多余的空格和换行
        $input = preg_replace('/\s+/', ' ', $input);
        $input = trim($input);

        // 8. 限制长度
        if (mb_strlen($input) > 3000) {
            $input = mb_substr($input, 0, 3000);
        }

        return $input;
    }
    
    
    
    
    
    if (!function_exists('LogWriteDaily') || !function_exists('logWriteDaily')) {
        /**
         * PHP 原生实现每天生成一个独立日志文件
         * 特性：
         * 1. 支持文本/数组/JSON 三种类型的日志消息，自动格式化
         * 2. 配置项（logDir/dateFormat/contentFormat）合并为数组，支持默认值+自定义
         * 3. 自动创建日志目录、加锁写入、异常处理
         * 
         * 测试用例（直接复制下方代码块执行即可验证功能）：
         * --------------------------------------------------------------------------------
         * // 测试1：文本类型消息（基础用法）
         * writeDailyLog("系统启动成功，端口8080", "INFO");
         * 
         * // 测试2：数组类型消息（自动转为格式化JSON）
         * $errorData = [
         *     'code' => 500,
         *     'msg' => '数据库连接失败',
         *     'details' => [
         *         'host' => '127.0.0.1',
         *         'port' => 3306,
         *         'error' => 'Access denied'
         *     ]
         * ];
         * LogWriteDaily($errorData, "ERROR");
         * 
         * // 测试3：JSON字符串消息（自动格式化）
         * $jsonStr = '{"order_id":"123456","status":"success","amount":99.9}';
         * LogWriteDaily($jsonStr, "DEBUG");
         * 
         * // 测试4：自定义配置+数组消息
         * LogWriteDaily(
         *     ['user_id' => 1001, 'action' => 'login', 'ip' => '127.0.0.1'],
         *     "INFO",
         *     ['logDir' => '/var/log/user_operate/']
         * );
         * 
         * // 测试5：验证写入结果
         * if (LogWriteDaily("多类型消息测试完成", "INFO")) {
         *     echo "日志写入成功！路径：" . __DIR__ . '/logs/' . date('Ymd') . '.log';
         * }
         * --------------------------------------------------------------------------------
         */

        /**
         * 写入日志的核心函数（兼容多类型消息）
         * @param string|array $message 日志消息（文本/数组/JSON字符串）
         * @param string $level 日志级别（INFO/ERROR/WARNING/DEBUG）
         * @param array $config 日志配置数组
         *        - logDir: 日志存储目录（默认：当前目录下的logs/）
         *        - dateFormat: 日志文件命名格式（默认：Ymd → 20260130）
         *        - contentFormat: 日志内容时间格式（默认：Y-m-d H:i:s）
         * @return bool 写入是否成功
         */
        function logWriteDaily(
                string|array $message,
                string $level = 'INFO',
                array $config = []
        ): bool {
            // 获取 storage/logs 目录（日志存储）
            $logPath = storage_path('logs');
            
            // 1. 定义默认配置数组
            $defaultConfig = [
                'logDir' => $logPath,
                'dateFormat' => 'Ymd',
                'contentFormat' => 'Y-m-d H:i:s'
            ];

            // 2. 合并默认配置和自定义配置
            $finalConfig = array_merge($defaultConfig, $config);
            $logDir = $finalConfig['logDir'];
            $dateFormat = $finalConfig['dateFormat'];
            $contentFormat = $finalConfig['contentFormat'];

            // 3. 格式化消息（处理文本/数组/JSON）
            $formattedMessage = formatLogMessage($message);

            // 4. 确保日志目录存在
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                    trigger_error("无法创建日志目录：" . $logDir, E_USER_ERROR);
                    return false;
                }
            }

            // 5. 生成日志文件路径（兼容目录末尾是否带/）
            // 检查 dateFormat 是否是日期格式字符串（以 Y, y, m, d, H, h, i, s 等开头）
            if (preg_match('/^[YyMmDdHhIiSsWwZzOoPpCc]/', $dateFormat)) {
                $logFileName = date($dateFormat) . '.log';
            } else {
                // 如果不是日期格式，直接作为文件名使用
                $logFileName = $dateFormat . '.log';
            }
            $logFilePath = rtrim($logDir, '/') . '/' . $logFileName;

            // 6. 构造规范的日志内容
            $time = date($contentFormat);
            $logContent = sprintf("[%s] [%s] %s\r\n", $time, strtoupper($level), $formattedMessage);

            // 7. 写入日志（追加模式+文件锁，防止多进程写入冲突）
            $result = file_put_contents(
                    $logFilePath,
                    $logContent,
                    FILE_APPEND | LOCK_EX
            );

            // 8. 错误处理：写入失败触发警告
            if ($result === false) {
                trigger_error("日志写入失败：" . $logFilePath, E_USER_WARNING);
                return false;
            }

            // 9. 设置日志文件权限（确保后续可读写）
            chmod($logFilePath, 0644);

            return true;
        }

        /**
         * 格式化日志消息（核心：自动处理文本/数组/JSON 类型）
         * @param string|array $message 原始消息
         * @return string 格式化后的可读字符串
         */
        function formatLogMessage(string|array $message): string {
            // 情况1：消息是数组 → 转为带缩进的JSON（保留中文，易读）
            if (is_array($message)) {
                return json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            // 情况2：消息是JSON字符串 → 解析后重新格式化（确保合法+结构清晰）
            $jsonDecoded = json_decode($message, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($jsonDecoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            // 情况3：普通文本 → 直接返回
            return $message;
        }
    }
    
}