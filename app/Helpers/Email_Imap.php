<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\CustomMail; // 新增：假设已创建自定义邮件类
use DateTime;
use DateTimeZone;

class Email_Imap {

    protected $imapStream; // IMAP 连接资源
    protected $server;      // IMAP 服务器地址（如 imap.qq.com）
    protected $port;        // 端口（如 993）
    protected $encryption;  // 加密方式（如 ssl）
    protected $username;    // 邮箱账号
    protected $password;    // 密码/授权码

    /**
     * 构造函数：初始化 IMAP 配置
     * @param string $server IMAP 服务器地址（如 imap.outlook.com）
     * @param int $port 端口（如 993）
     * @param string $encryption 加密方式（如 ssl）
     * @param string $username 邮箱账号
     * @param string $password 密码/授权码
     * @param bool $novalidateCert 是否跳过证书验证（默认 false）
     */
    public function __construct(
            string $server,
            int $port,
            string $encryption,
            string $username,
            string $password,
            bool $novalidateCert = false
    ) {
        $this->server = $server;
        $this->port = $port;
        $this->encryption = $encryption;
        $this->username = $username;
        $this->password = $password;
        $this->novalidateCert = $novalidateCert;
    }

//    public function setConfig($host="pop.163.com",$port=995,$username="user@163.com",$password="password",$ssl="true") {
//        $this->pop3Config["host"]=$host;
//        $this->pop3Config["port"]=$port;
//        $this->pop3Config["username"]=$username;
//        $this->pop3Config["password"]=$password;
//        $this->pop3Config["ssl"]=$ssl;
//    }

    /**
     * 连接 IMAP 服务器
     * @param string $folder 要连接的文件夹名称，默认为 INBOX
     * @return bool 连接成功返回 true，失败返回 false
     */
    public function connect(string $folder = 'INBOX'): bool {
        $serverString = sprintf(
                '{%s:%d/imap/%s%s}%s',
                $this->server,
                $this->port,
                $this->encryption,
                $this->novalidateCert ? '/novalidate-cert' : '',
                $folder
        );

        Log::info('IMAP 连接尝试', [
            'server' => $serverString,
            'username' => $this->username,
            'password' => $this->password,
            'folder' => $folder
        ]);

        $this->imapStream = imap_open($serverString, $this->username, $this->password);
        if (!$this->imapStream) {
            Log::error('IMAP 连接失败', ['error' => imap_last_error(), 'folder' => $folder]);
            return false;
        }
        return true;
    }
    
    /**
     * 切换到指定文件夹
     * @param string $folder 文件夹名称
     * @param bool $tryAlternatives 是否尝试替代文件夹（针对Gmail等特殊邮箱）
     * @return bool 切换成功返回 true，失败返回 false
     */
    public function switchFolder(string $folder, bool $tryAlternatives = true): bool {
        if (!$this->imapStream) {
            Log::error('IMAP 未连接，无法切换文件夹');
            return false;
        }
        
        $serverString = sprintf('{%s:%d/imap/%s%s}%s', 
                $this->server, $this->port, $this->encryption, 
                $this->novalidateCert ? '/novalidate-cert' : '', $folder);
        
        $result = imap_reopen($this->imapStream, $serverString);
        
        // 如果切换失败且启用了替代方案尝试
        if (!$result && $tryAlternatives && strpos($this->server, 'gmail.com') !== false) {
            Log::info('尝试Gmail特殊文件夹格式', ['original_folder' => $folder]);
            
            // 准备Gmail的替代文件夹格式
            $gmailAlternatives = [];
            
            // 根据原始文件夹生成替代方案
            if (strtolower($folder) === 'sent' || strtolower($folder) === 'sent mail') {
                $gmailAlternatives = ['[Gmail]/Sent Mail', '[Gmail]/已发送邮件'];
            } elseif (strtolower($folder) === 'inbox') {
                $gmailAlternatives = ['[Gmail]/收件箱'];
            } else {
                // 通用转换
                $gmailAlternatives = ['[Gmail]/' . $folder];
            }
            
            // 尝试每个替代文件夹
            foreach ($gmailAlternatives as $altFolder) {
                $altServerString = sprintf('{%s:%d/imap/%s%s}%s', 
                        $this->server, $this->port, $this->encryption, 
                        $this->novalidateCert ? '/novalidate-cert' : '', $altFolder);
                
                if (imap_reopen($this->imapStream, $altServerString)) {
                    Log::info('成功切换到Gmail替代文件夹', ['folder' => $altFolder]);
                    return true;
                }
            }
        }
        
        if (!$result) {
            Log::error('切换文件夹失败', ['error' => imap_last_error(), 'folder' => $folder]);
            return false;
        }
        
        Log::info('成功切换到文件夹', ['folder' => $folder]);
        return true;
    }

    /**
     * 获取邮箱中邮件总数
     * @return int 邮件总数
     */
    public function getMessageCount(): int {
        return imap_num_msg($this->imapStream);
    }

    /**
     * 搜索未读邮件 UID 列表
     * @return array|false 未读邮件 UID 数组（失败返回 false）
     */
    public function searchUnseenMails() {
        return imap_search($this->imapStream, 'UNSEEN', SE_UID);
    }

    /**
     * 获取最近 N 封邮件的 UID 列表
     * @param int $limit 最大数量（默认 5）
     * @return array UID 数组
     */
    public function getRecentUids(int $limit = 5): array {
        $uids = imap_sort($this->imapStream, SORTARRIVAL, 1, SE_UID);
        return is_array($uids) ? array_slice($uids, 0, $limit) : [];
    }

    /**
     * 获取邮件详情（主题、发件人、日期、内容预览）
     * @param string $uid 邮件 UID
     * @return array 邮件详情数组
     */
    public function getMailDetails(string $uid): array {
        $msgNo = imap_msgno($this->imapStream, $uid);
        
        // 验证消息编号是否有效（必须大于0）
        if (!$msgNo || $msgNo <= 0) {
            Log::error('无效的邮件消息编号', ['uid' => $uid, 'msgNo' => $msgNo]);
            return [
                'uid' => $uid,
                'subject' => '(无法获取)',
                'from' => '未知',
                'date' => date('Y-m-d H:i:s'),
                'content' => '无法获取邮件内容',
                'preview' => '无法获取邮件内容...'
            ];
        }
        
        $headers = imap_headerinfo($this->imapStream, $msgNo);
        $structure = imap_fetchstructure($this->imapStream, $msgNo);

        Log::info("EmailImap->getMailDetails($uid)");
        $content = '';
        if (isset($structure->parts) && count($structure->parts) > 0) {
            foreach ($structure->parts as $index => $part) {
                if (strtolower($part->subtype) === 'plain' && $part->type === 0) {
                    $body = imap_fetchbody($this->imapStream, $msgNo, $index + 1);

                    Log::info("EmailImap->getMailDetails($uid)", ["bodySize" => strlen($body)]);
                    $content = $this->decodeBody($body, $part->encoding);
//                    Log::info("EmailImap->getMailDetails($uid)",["decodeBody"=>$content]);
                    break;
                }
            }
        }

        // 创建DateTime对象并指定原始时间和时区
        $date = DateTime::createFromFormat('D, d M Y H:i:s e', $headers->date);
        
        // 检查DateTime对象是否创建成功，避免setTimezone()调用错误
        if ($date === false) {
            // 如果日期格式不匹配，尝试其他常见格式或使用当前时间作为默认值
            try {
                $date = new DateTime($headers->date ?? 'now');
            } catch (Exception $e) {
                // 如果所有解析尝试都失败，使用当前时间
                $date = new DateTime('now');
            }
        }
        
        // 设置时区为北京时间（Asia/Shanghai）
        $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
        // 转换为目标格式
        $formattedTime = $date->format('Y-m-d H:i:s');
        return [
            'uid' => $uid,
            'subject' => $headers->subject ?? '(无主题)',
            'from' => $headers->fromaddress ?? '未知',
            'date' => $formattedTime ?? '',
            'content' => $content ?? '未知',
            'preview' => substr(strip_tags($content), 0, 100) . '...'
        ];
    }

    /**
     * 标记邮件为已读
     * @param string $uid 邮件 UID
     * @return bool 操作成功返回 true
     */
    public function markAsRead(string $uid): bool {
        return imap_setflag_full($this->imapStream, $uid, '\\Seen', ST_UID);
    }
    
    /**
     * 搜索邮件并返回邮件ID列表 Parameters
     * @param string $criteria 搜索条件，默认为'ALL'（所有邮件）  <br/>
                                        * ALL - 返回符合其余所有条件的所有消息  <br/>
                                        * ANSWERED - 将带有“已接听”标志的记录与这些消息进行匹配  <br/>
                                        * BCC "string" - 将包含“字符串”内容的邮件与“抄送：”字段中的“字符串”进行匹配  <br/>
                                        * BEFORE "date" - 将消息与日期匹配：在“日期”之前的部分  <br/>
                                        * BODY "string" - 将包含在消息正文中的“字符串”内容与消息进行匹配  <br/>
                                        * CC "string" - 将包含“字符串”内容的“抄送”字段中的消息与之进行匹配  <br/>
                                        * DELETED - 删除的已匹配消息
     * @param bool $descending 是否按日期降序排序，默认为true
     * @return array|false 返回邮件ID数组，失败或无邮件时返回空数组
     */
    public function searchEmails(string $criteria = 'ALL', bool $descending = true): array {
        if (!$this->imapStream) {
            Log::error('IMAP 未连接，无法搜索邮件');
            return [];
        }
        
        Log::info('搜索邮件', ['criteria' => $criteria]);
        
        // 使用imap_search搜索邮件
        $emails = imap_search($this->imapStream, $criteria);
        
        // 检查搜索结果
        if (!$emails) {
            Log::info('未找到匹配条件的邮件', ['criteria' => $criteria]);
            return [];
        }
        
        // 按日期排序（如果需要）
        if ($descending) {
            rsort($emails); // 降序排序（最新邮件在前）
        } else {
            sort($emails); // 升序排序（最旧邮件在前）
        }
        
        Log::info('搜索到邮件', ['count' => count($emails), 'criteria' => $criteria]);
        return $emails;
    }

    /**
     * 获取邮箱文件夹列表
     * @return array 文件夹名称数组
     */
    public function getFolders(): array {
        $folders = imap_list($this->imapStream, $this->getServerString(), '*');
        return array_map(function ($folder) {
            return str_replace($this->getServerString(), '', $folder);
        }, $folders);
    }

    /**
     * 关闭 IMAP 连接
     * @return void
     */
    public function close(): void {
        if ($this->imapStream) {
            imap_close($this->imapStream);
        }
    }

    /**
     * 解码邮件正文内容（处理 base64/quoted-printable 编码）
     * @param string $body 原始内容
     * @param int $encoding 编码类型（3=base64，4=quoted-printable）
     * @return string 解码后的内容
     */
    protected function decodeBody(string $body, int $encoding): string {
        switch ($encoding) {
            case 3:
                return base64_decode($body);
            case 4:
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * 获取服务器连接字符串（用于文件夹解析）
     * @param string $folder 文件夹名称，默认为 INBOX
     * @return string 服务器连接字符串
     */
    protected function getServerString(string $folder = 'INBOX'): string {
        return sprintf(
                '{%s:%d/imap/%s%s}%s',
                $this->server,
                $this->port,
                $this->encryption,
                $this->novalidateCert ? '/novalidate-cert' : '',
                $folder
        );
    }

    /**
     * 获取最新一封邮件的 UID（即最近接收的邮件）
     * @description 通过 IMAP 协议获取邮箱中最新接收的邮件唯一标识（UID）
     * @return string|false 最新邮件 UID（无邮件时返回 false）
     */
    public function getLatestMailUid(): string|false {
        $recentUids = $this->getRecentUids(1); // 获取最近1封邮件的 UID 列表
        if (empty($recentUids)) {
            Log::warning('当前邮箱无邮件，无法获取最新邮件 UID');
            return false;
        }
        return $recentUids[0]; // 返回最新邮件的 UID
    }

    /**
     * 发送邮件（基于 SMTP 协议）
     * @param string|array $to 收件人邮箱（支持单个或数组）
     * @param string $subject 邮件主题
     * @param string $content 邮件正文（HTML 或纯文本）
     * @param array $attachments 附件路径数组（可选）
     * @return bool 发送成功返回 true
     */
    public function sendMail(string|array $to, string $subject, string $content, array $attachments = []): bool {
        try {
            // 验证必要参数
            if (empty($to) || empty($subject) || empty($content)) {
                Log::error('邮件发送失败：缺少必要参数（收件人、主题或正文）');
                return false;
            }

            // 使用 Laravel Mail 发送（需提前在 .env 配置 SMTP）
//            $mail = Mail::to($to)
//                        ->subject($subject)
//                        ->html($content);
            // 添加附件
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->attach($filePath);
                }
            }

            // 记录发送日志
            Log::info('邮件发送请求', [
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject,
                'attachments' => count($attachments)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('邮件发送失败', [
                'error' => $e->getMessage(),
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject
            ]);
            return false;
        }
    }

    /**
     * 发送邮件（基于原生 SMTP 协议，使用 PHPMailer）
     * @param string|array $to 收件人邮箱（支持单个或数组）
     * @param string $subject 邮件主题
     * @param string $content 邮件正文（HTML 或纯文本）
     * @param array $attachments 附件路径数组（可选）
     * @return bool 发送成功返回 true
     */
    public function sendTestMail(string|array $to, string $subject, string $content, array $attachments = []): bool {
        try {
            // 验证必要参数
            if (empty($to) || empty($subject) || empty($content)) {
                Log::error('邮件发送失败：缺少必要参数（收件人、主题或正文）');
                return false;
            }

            // 初始化 PHPMailer（启用异常）
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // SMTP 配置（根据实际邮箱调整，示例为 Outlook/Office365 配置）
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';       // SMTP 服务器（如 Gmail 为 smtp.gmail.com）
            $mail->SMTPAuth = true;               // 启用 SMTP 认证
            $mail->Username = $this->username;    // 邮箱账号（如 user@outlook.com）
            $mail->Password = $this->password;    // 邮箱密码/授权码
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;  // TLS 加密
            $mail->Port = 587;                    // SMTP 端口（TLS 通常为 587，SSL 为 465）
            // 发件人信息
            $mail->CharSet = 'UTF-8';  // 设置字符编码为 UTF-8
            $mail->Encoding = 'base64'; // 设置编码方式
            $mail->setFrom($this->username, 'Server Email');  // 发件人邮箱和名称
            // 收件人（支持多个）
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $mail->addAddress($recipient);
                }
            } else {
                $mail->addAddress($to);
            }

            // 邮件内容
            $mail->isHTML(true);                         // 启用 HTML 格式
            $mail->Subject = $subject;
            
            // 处理邮件模板
            if (!empty($orther["template"])) {
                // 使用模板渲染邮件内容
                $templateData = $orther["template_data"] ?? [];
                $templateData['content'] = $content;
                $templateData['subject'] = $subject;
                
                try {
                    // 渲染模板
                    $mail->Body = view($orther["template"], $templateData)->render();
                    Log::info('使用邮件模板', ['template' => $orther["template"], 'data_keys' => array_keys($templateData)]);
                } catch (\Exception $e) {
                    Log::warning('模板渲染失败，使用原始内容', [
                        'template' => $orther["template"],
                        'error' => $e->getMessage()
                    ]);
                    $mail->Body = $content;
                }
            } else {
                // 使用原始内容
                $mail->Body = $content;
            }

            // 添加附件
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);       // 支持绝对路径或相对路径
                }
            }

            // 发送邮件
            $mail->send();

            // 记录成功日志
            Log::info('邮件发送成功', [
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject,
                'attachments' => count($attachments)
            ]);
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // 记录详细错误（包含 PHPMailer 原生错误信息）
            Log::error('邮件发送失败', [
                'error' => $mail->ErrorInfo,
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject
            ]);
            return false;
        }
    }
    
    
    
    /**
     * 发送邮件（基于原生 SMTP 协议，使用 PHPMailer）
     * @param string|array $to 收件人邮箱（支持单个或数组）
     * @param string $subject 邮件主题
     * @param string $content 邮件正文（HTML 或纯文本）
     * @param array $attachments 附件路径数组（可选）
     * @param array $orther 其他参数    邮件回复人设置：<br/>
                                        * 支持单个回复邮箱： ["reply_to" => "b@gmail.com"]<br/>
                                        * 支持多个回复邮箱： ["reply_to" => ["b@gmail.com", "c@gmail.com"]]<br/>
                                        * 可选增强功能：
                                        * 发件人名称： ["from_name" => "订单验货系统"]<br/>
                                        * 抄送设置： ["cc" => "cc@example.com"]<br/>
                                        * 密送设置： ["bcc" => "bcc@example.com"]<br/>
                                        * 邮件模板与数据： [<br/>"template" => "emails.order_inspection <resources\views\emails\order_inspection.blade.php>",<br/>"template_data" => ["order_number" => "ORD123456","customer_name" => "张三","product_type" => "电子产品",<br/>"additional_info" => "其他补充信息"<br/>]]
     * 
     * @return bool 发送成功返回 true
     */
    public function sendTestMailOrther(string|array $to, string $subject="SaveBullet Order inspection ", string $content, array $attachments = [] ,array $orther=[]): bool {
        try {
            // 验证必要参数
            if (empty($to) || empty($subject) || empty($content)) {
                Log::error('邮件发送失败：缺少必要参数（收件人、主题或正文）');
                return false;
            }else{
//                $subject="SaveBullet Order inspection ".$subject;
                
            }
            // 初始化 PHPMailer（启用异常）
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // SMTP 配置（根据实际邮箱调整，示例为 Outlook/Office365 配置）
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';       // SMTP 服务器（如 Gmail 为 smtp.gmail.com）
            $mail->SMTPAuth = true;               // 启用 SMTP 认证
            $mail->Username = $this->username;    // 邮箱账号（如 user@outlook.com）
            $mail->Password = $this->password;    // 邮箱密码/授权码
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;  // TLS 加密
            $mail->Port = 587;                    // SMTP 端口（TLS 通常为 587，SSL 为 465）
            // 发件人信息
            $mail->CharSet = 'UTF-8';  // 设置字符编码为 UTF-8
            $mail->Encoding = 'base64'; // 设置编码方式
            $mail->setFrom($this->username, $subject);  // 发件人邮箱和名称
            // 收件人（支持多个）
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $mail->addAddress($recipient);
                }
            } else {
                $mail->addAddress($to);
            }

            
            
            if(!empty($orther)){
                if(!empty($orther["reply_to"])){
                    // 设置邮件回复人
                    if (is_array($orther["reply_to"])) {
                        foreach ($orther["reply_to"] as $replyTo) {
                            $mail->addReplyTo($replyTo);
                        }
                    } else {
                        $mail->addReplyTo($orther["reply_to"]);
                    }
                    Log::info('设置邮件回复人', ['reply_to' => $orther["reply_to"]]);
                }
                
                // 可选：设置发件人名称
                if(!empty($orther["from_name"])){
                    // 处理中文乱码问题
                    $fromName = $orther["from_name"];
                    // 确保字符编码正确
                    if (mb_detect_encoding($fromName, 'UTF-8', true) === false) {
                        $fromName = mb_convert_encoding($fromName, 'UTF-8', 'auto');
                    }
                    $mail->setFrom($this->username, $fromName);
                    Log::info('设置发件人名称', ['from_name' => $fromName]);
                }
                
                // 可选：设置抄送
                if(!empty($orther["cc"])){
                    if (is_array($orther["cc"])) {
                        foreach ($orther["cc"] as $cc) {
                            $mail->addCC($cc);
                        }
                    } else {
                        $mail->addCC($orther["cc"]);
                    }
                }
                
                // 可选：设置密送
                if(!empty($orther["bcc"])){
                    if (is_array($orther["bcc"])) {
                        foreach ($orther["bcc"] as $bcc) {
                            $mail->addBCC($bcc);
                        }
                    } else {
                        $mail->addBCC($orther["bcc"]);
                    }
                }
            }
            
            
            
            // 邮件内容
            $mail->isHTML(true);                         // 启用 HTML 格式
            $mail->Subject = $subject;
            
            // 处理邮件模板
            if (!empty($orther["template"])) {
                // 使用模板渲染邮件内容
                $templateData = $orther["template_data"] ?? [];
                $templateData['content'] = $content;
                $templateData['subject'] = $subject;
                
                try {
                    // 渲染模板
                    $mail->Body = view($orther["template"], $templateData)->render();
                    Log::info('使用邮件模板', ['template' => $orther["template"], 'data_keys' => array_keys($templateData)]);
                } catch (\Exception $e) {
                    Log::warning('模板渲染失败，使用原始内容', [
                        'template' => $orther["template"],
                        'error' => $e->getMessage()
                    ]);
                    $mail->Body = $content;
                }
            } else {
                // 使用原始内容
                $mail->Body = $content;
            }

            // 添加附件
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);       // 支持绝对路径或相对路径
                }
            }

            // 发送邮件
            $mail->send();

            // 记录成功日志
            Log::info('邮件发送成功', [
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject,
                'attachments' => count($attachments)
            ]);
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // 记录详细错误（包含 PHPMailer 原生错误信息）
            Log::error('邮件发送失败', [
                'error' => $mail->ErrorInfo,
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject
            ]);
            return false;
        }
    }

}
