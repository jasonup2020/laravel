## 公用方法 app\Helpers\common.php

| 分类 | 方法名 | 说明 |
|------|--------|------|
| **数组操作** | `array_sort($arr, $keys, $desc = false)` | 数组排序 |
| | `array_merge_multiple($array1, $array2)` | 多维数组合并 |
| | `array_key_value($arr, $name = "")` | 获取数组中某个字段的所有值 |
| | `xml2array($xml)` | XML转数组 |
| | `array2xml($arr, $ignore = true, $level = 1)` | 数组转XML |
| **HTTP请求** | `curl_url()` | 获取当前访问的完整地址 |
| | `curl_get($url, $data = [])` | curl GET请求 |
| | `curl_post($url, $data = [])` | curl POST请求 |
| | `curl_request($url, $data = [], $type = 'post', $https = false, $httpheader = [])` | curl通用请求 |
| **数据格式化** | `datetime($time, $format = 'Y-m-d H:i:s')` | 格式化日期 |
| | `format_time($time)` | 格式化时间段 |
| | `format_bytes($size, $delimiter = '')` | 字节转换为可读文本 |
| | `format_yuan($money = 0)` | 分转元 |
| | `format_cent($money)` | 元转分 |
| | `format_bank_card($card_no, $is_format = true)` | 银行卡格式转换 |
| | `format_mobile($mobile)` | 手机号格式化 |
| | `sub_str($str, $start = 0, $length = 10, $suffix = true, $charset = "utf-8")` | 字符串截取 |
| | `strip_html_tags($str, $length = 0)` | 去除HTML标签 |
| **安全相关** | `data_auth_sign($data)` | 数据签名认证 |
| | `encrypt($str, $key = 'p@ssw0rd')` | DES加密 |
| | `decrypt($str, $key = 'p@ssw0rd')` | DES解密 |
| | `get_password($password)` | 双MD5加密密码 |
| | `get_random_code($num = 12)` | 获取随机码 |
| | `get_hash()` | 获取HASH值 |
| **业务工具** | `get_order_num($prefix = '')` | 生成订单号 |
| | `get_guid_v4($trim = true)` | 生成GUID V4 |
| | `get_zodiac_sign($month, $day)` | 获取星座 |
| | `is_email($str)` | 验证邮箱 |
| | `is_mobile($num)` | 验证手机号 |
| | `is_zipcode($code)` | 验证邮编 |
| | `is_idcard($idno)` | 验证身份证 |
| | `is_empty($value)` | 判断是否为空 |
| **文件操作** | `mkdirs($dir, $mode = 0777)` | 递归创建目录 |
| | `rmdirs($dir, $rmself = true)` | 递归删除目录 |
| | `copydirs($source, $dest)` | 递归复制目录 |
| | `save_image($img_url, $save_dir = '/')` | 保存远程图片 |
| | `create_image_path($save_dir = "", $image_ext = "", $image_root = IMG_PATH)` | 创建图片路径 |
| | `save_remote_image($img_url, $save_dir = '/')` | 保存远程图片 |
| | `save_image_content(&$content, $title = false, $path = 'article')` | 保存图片内容 |
| | `upload_image($request, $form_name = 'file')` | 上传图片 |
| | `upload_file($request, $form_name = 'file')` | 上传文件 |
| **响应输出** | `message($msg = "操作成功", $success = true, $data = null, $code = 200, $extra = null)` | 统一响应格式 |
| | `success($message = 'Success', $data = null, $code = 200)` | 成功响应 |
| | `error($message = 'Error', $code = 400, $errors = null)` | 错误响应 |
| | `format_pagination($paginator)` | 格式化分页数据 |
| **其他工具** | `getter($data, $field, $default = '')` | 获取数组下标值 |
| | `get_image_url($image_url)` | 获取图片网络地址 |
| | `get_server_ip()` | 获取服务器IP |
| | `get_client_ip($type = 0, $adv = false)` | 获取客户端IP |
| | `export_excel($file_name, $title = [], $data = [])` | 导出Excel |
| | `ecm_define($value)` | 定义常量 |
| | `num2rmb($num)` | 数字转人民币大写 |
| | `object_array($object)` | 对象转数组 |
| | `parse_attr($value = '')` | 解析属性 |
| | `widget($widgetName)` | 获取小部件 |
| | `logWriteDaily(string|array $message, string $level = 'INFO', array $config = [])` | 日志写入 |
| | `formatLogMessage(string|array $message): string` | 格式化日志消息 |
