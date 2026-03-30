<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * 业务异常类
 * 
 * 用于业务逻辑中抛出的异常，统一处理响应格式
 * 
 * @package App\Exceptions
 * @author  SaaS Platform
 * @version 1.0.0
 */
class BusinessException extends Exception
{
    /**
     * 错误数据
     *
     * @var mixed
     */
    protected $data;

    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $code 错误码
     * @param mixed $data 错误数据
     * @param Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = '', int $code = 400, $data = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * 获取错误数据
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 渲染异常为HTTP响应
     *
     * @return JsonResponse
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data ?? new \stdClass(),
            'success' => false
        ], $this->code >= 400 && $this->code < 600 ? $this->code : 400);
    }

    /**
     * 创建参数错误异常
     *
     * @param string $message 错误消息
     * @param mixed $data 错误数据
     * @return static
     */
    public static function paramError(string $message = '参数错误', $data = null): static
    {
        return new static($message, 400, $data);
    }

    /**
     * 创建未授权异常
     *
     * @param string $message 错误消息
     * @return static
     */
    public static function unauthorized(string $message = '未授权'): static
    {
        return new static($message, 401);
    }

    /**
     * 创建禁止访问异常
     *
     * @param string $message 错误消息
     * @return static
     */
    public static function forbidden(string $message = '禁止访问'): static
    {
        return new static($message, 403);
    }

    /**
     * 创建未找到异常
     *
     * @param string $message 错误消息
     * @return static
     */
    public static function notFound(string $message = '资源未找到'): static
    {
        return new static($message, 404);
    }

    /**
     * 创建验证失败异常
     *
     * @param string $message 错误消息
     * @param mixed $data 错误数据
     * @return static
     */
    public static function validationError(string $message = '验证失败', $data = null): static
    {
        return new static($message, 422, $data);
    }

    /**
     * 创建服务器错误异常
     *
     * @param string $message 错误消息
     * @return static
     */
    public static function serverError(string $message = '服务器错误'): static
    {
        return new static($message, 500);
    }
}
