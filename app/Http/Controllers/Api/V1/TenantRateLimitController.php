<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\TenantRateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 租户限流控制器
 * 
 * 处理租户限流配置相关操作，包括获取配置、设置配置、获取状态、重置限流等
 * 
 * @method JsonResponse getConfig(int $tenantId) 获取租户限流配置
 * @method JsonResponse setConfig(Request $request, int $tenantId) 设置租户限流配置
 * @method JsonResponse getStatus(int $tenantId) 获取租户限流状态
 * @method JsonResponse reset(Request $request, int $tenantId) 重置租户限流
 */
class TenantRateLimitController extends BaseController
{
    /**
     * 限流服务实例
     *
     * @var TenantRateLimitService
     */
    protected TenantRateLimitService $rateLimitService;

    /**
     * 构造函数
     *
     * @param TenantRateLimitService $rateLimitService 限流服务
     */
    public function __construct(TenantRateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * 获取租户限流配置
     *
     * 获取指定租户的限流配置信息
     *
     * @param int $tenantId 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam tenantId int required 租户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "requests_per_minute": 60,
     *     "requests_per_hour": 1000,
     *     "requests_per_day": 10000
     *   }
     * }
     */
    public function getConfig(int $tenantId): JsonResponse
    {
        $config = $this->rateLimitService->getRateLimitConfig($tenantId);
        
        return $this->success($config);
    }

    /**
     * 设置租户限流配置
     *
     * 设置指定租户的限流配置
     *
     * @param Request $request HTTP请求对象
     * @param int $tenantId 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam tenantId int required 租户ID Example: 1
     * @bodyParam requests_per_minute int 可选 每分钟请求数限制 (1-10000) Example: 60
     * @bodyParam requests_per_hour int 可选 每小时请求数限制 (1-100000) Example: 1000
     * @bodyParam requests_per_day int 可选 每天请求数限制 (1-1000000) Example: 10000
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": null
     * }
     */
    public function setConfig(Request $request, int $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'requests_per_minute' => 'nullable|integer|min:1|max:10000',
            'requests_per_hour' => 'nullable|integer|min:1|max:100000',
            'requests_per_day' => 'nullable|integer|min:1|max:1000000',
        ], [], [
            'requests_per_minute' => __('validation.attributes.requests_per_minute'),
            'requests_per_hour' => __('validation.attributes.requests_per_hour'),
            'requests_per_day' => __('validation.attributes.requests_per_day'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $config = $request->all();
        
        $result = $this->rateLimitService->setRateLimitConfig($tenantId, $config);
        
        if ($result) {
            return $this->success(null, __('messages.update_success'));
        }
        
        return $this->error(__('messages.update_failed'));
    }

    /**
     * 获取租户限流状态
     *
     * 获取指定租户当前的限流状态信息
     *
     * @param int $tenantId 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam tenantId int required 租户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "current_minute": 10,
     *     "current_hour": 150,
     *     "current_day": 1200,
     *     "limit_reached": false
     *   }
     * }
     */
    public function getStatus(int $tenantId): JsonResponse
    {
        $status = $this->rateLimitService->getRateLimitStatus($tenantId);
        
        return $this->success($status);
    }

    /**
     * 重置租户限流
     *
     * 重置指定租户的限流计数器
     *
     * @param Request $request HTTP请求对象
     * @param int $tenantId 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam tenantId int required 租户ID Example: 1
     * @bodyParam endpoint string 可选 要重置的端点，不传则重置所有 Example: /api/v1/users
     * 
     * @response {
     *   "code": 200,
     *   "message": "重置成功",
     *   "data": null
     * }
     */
    public function reset(Request $request, int $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'nullable|string|max:255',
        ], [], [
            'endpoint' => __('validation.attributes.endpoint'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $endpoint = $request->input('endpoint');
        
        $result = $this->rateLimitService->resetRateLimit($tenantId, $endpoint);
        
        if ($result) {
            return $this->success(null, __('messages.reset_success'));
        }
        
        return $this->error(__('messages.reset_failed'));
    }
}
