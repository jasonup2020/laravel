<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

/**
 * XSS输入净化中间件
 * 
 * 自动净化请求输入，防止XSS攻击
 */
class SanitizeInput
{
    /**
     * 需要净化的字段类型
     */
    protected array $sanitizeFields = [
        'name',
        'title',
        'description',
        'content',
        'remark',
    ];

    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next)
    {
        // 只净化输入数据，不净化密码等敏感字段
        $input = $request->all();
        
        foreach ($input as $key => $value) {
            if (is_string($value) && $this->shouldSanitize($key)) {
                $input[$key] = $this->sanitize($value);
            }
        }
        
        $request->replace($input);
        
        return $next($request);
    }

    /**
     * 判断字段是否需要净化
     */
    protected function shouldSanitize(string $key): bool
    {
        // 密码字段不净化
        if (strpos($key, 'password') !== false) {
            return false;
        }
        
        // Token字段不净化
        if (strpos($key, 'token') !== false) {
            return false;
        }
        
        // Email字段不净化（有专门验证）
        if ($key === 'email') {
            return false;
        }
        
        // 指定字段净化
        if (in_array($key, $this->sanitizeFields)) {
            return true;
        }
        
        // 默认净化所有字符串字段
        return true;
    }

    /**
     * 净化字符串
     */
    protected function sanitize(string $value): string
    {
        // 如果包含HTML标签，则净化
        if ($value !== strip_tags($value)) {
            // 移除所有HTML标签
            $clean = strip_tags($value);
            
            // 如果净化后为空，返回原始值（让验证器处理）
            if (empty(trim($clean))) {
                return $value;
            }
            
            return $clean;
        }
        
        return $value;
    }
}
