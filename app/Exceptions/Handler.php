<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * 全局异常处理器
 * 
 * 统一处理所有异常，返回标准化的JSON响应
 * 
 * @package App\Exceptions
 * @author  SaaS Platform
 * @version 1.0.0
 */
class Handler extends ExceptionHandler
{
    /**
     * 不需要报告的异常类型
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        BusinessException::class,
    ];

    /**
     * 不需要报告的异常状态码
     *
     * @var array<int, int>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * 注册异常处理回调
     *
     * @return void
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // 记录异常日志
            if ($this->shouldReport($e)) {
                log_error('Exception: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * 渲染异常为HTTP响应
     *
     * @param Request $request 请求
     * @param Throwable $e 异常
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        // API请求统一返回JSON格式
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * 处理API异常
     *
     * @param Request $request 请求
     * @param Throwable $e 异常
     * @return JsonResponse
     */
    protected function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        // 业务异常
        if ($e instanceof BusinessException) {
            return $e->render();
        }

        // 验证异常
        if ($e instanceof ValidationException) {
            return $this->handleValidationException($e);
        }

        // HTTP异常
        if ($e instanceof HttpException) {
            return $this->handleHttpException($e);
        }

        // 其他异常
        return $this->handleGenericException($e);
    }

    /**
     * 处理验证异常
     *
     * @param ValidationException $e 异常
     * @return JsonResponse
     */
    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        $errors = $e->errors();
        $firstError = collect($errors)->first();
        $message = is_array($firstError) ? $firstError[0] : $firstError;

        return response()->json([
            'code' => 422,
            'message' => $message,
            'data' => [
                'errors' => $errors
            ],
            'success' => false
        ], 422);
    }

    /**
     * 处理HTTP异常
     *
     * @param HttpException $e 异常
     * @return JsonResponse
     */
    protected function handleHttpException(HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage() ?: $this->getDefaultMessage($statusCode);

        // 特殊HTTP异常处理
        if ($e instanceof NotFoundHttpException) {
            $message = __('messages.not_found');
        } elseif ($e instanceof UnauthorizedHttpException) {
            $message = __('messages.unauthorized');
        } elseif ($e instanceof AccessDeniedHttpException) {
            $message = __('messages.forbidden');
        }

        return response()->json([
            'code' => $statusCode,
            'message' => $message,
            'data' => new \stdClass(),
            'success' => false
        ], $statusCode);
    }

    /**
     * 处理通用异常
     *
     * @param Throwable $e 异常
     * @return JsonResponse
     */
    protected function handleGenericException(Throwable $e): JsonResponse
    {
        $statusCode = 500;
        $message = config('app.debug') ? $e->getMessage() : __('messages.server_error');

        $data = new \stdClass();

        // 调试模式下返回详细信息
        if (config('app.debug')) {
            $data = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->map(function ($trace) {
                    return \Illuminate\Support\Arr::except($trace, ['args']);
                })->all()
            ];
        }

        return response()->json([
            'code' => $statusCode,
            'message' => $message,
            'data' => $data,
            'success' => false
        ], $statusCode);
    }

    /**
     * 获取默认错误消息
     *
     * @param int $statusCode 状态码
     * @return string
     */
    protected function getDefaultMessage(int $statusCode): string
    {
        $messages = [
            400 => __('messages.bad_request'),
            401 => __('messages.unauthorized'),
            403 => __('messages.forbidden'),
            404 => __('messages.not_found'),
            405 => __('messages.method_not_allowed'),
            422 => __('messages.validation_error'),
            429 => __('messages.too_many_requests'),
            500 => __('messages.server_error'),
            503 => __('messages.service_unavailable'),
        ];

        return $messages[$statusCode] ?? __('messages.error');
    }

    /**
     * 判断是否需要报告异常
     *
     * @param Throwable $e 异常
     * @return bool
     */
    protected function shouldReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        return true;
    }
}
