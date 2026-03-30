<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use function PHPSTORM_META\map;

/**
 * 基础控制器类
 * 
 * 继承Controller，提供通用功能：
 * - 统一响应格式
 * - 参数验证
 * - 异常处理
 * - 日志记录
 * 
 * @package App\Http\Controllers
 * @author  SaaS Platform
 * @version 1.0.0
 */
abstract class BaseController extends Controller
{

    /**
     * 服务实例
     *
     * @var mixed
     */
    protected $service;

    /**
     * 返回成功响应
     *
     * @param mixed $data 数据
     * @param string $message 消息
     * @param int $code 状态码
     * @return JsonResponse
     */
    protected function success($data = null, string $message = '', int $code = 200): JsonResponse
    {
        return message(
            $message ?: __('messages.success'),
            true,
            $data,
            $code
        );
    }

    /**
     * 返回错误响应
     *
     * @param string $message 错误消息
     * @param int $code 状态码
     * @param mixed $data 数据
     * @return JsonResponse
     */
    protected function error(string $message = '', int $code = 400, $data = null): JsonResponse
    {
        return message(
            $message ?: __('messages.failed'),
            false,
            $data,
            $code
        );
    }

    /**
     * 返回消息响应
     *
     * @param string $message 消息
     * @param bool $success 是否成功
     * @param mixed $data 数据
     * @param int $code 状态码
     * @param mixed $extra 额外数据
     * @return JsonResponse
     */
    protected function message(
        string $message,
        bool $success = true,
        $data = null,
        int $code = 200,
        $extra = null
    ): JsonResponse {
        $response = [
            'message' => $message,
            'success' => $success,
            'data' => $data,
            'code' => $code,
        ];

        if ($extra !== null) {
            $response['extra'] = $extra;
        }

        return response()->json($response, $code);
    }

    /**
     * 返回分页数据
     *
     * @param mixed $paginator Laravel分页对象
     * @param string $message 消息
     * @return JsonResponse
     */
    protected function paginate($paginator, string $message = ''): JsonResponse
    {
        return $this->success([
            'list' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages()
            ]
        ], $message);
    }

    /**
     * 验证请求参数
     *
     * @param Request $request 请求
     * @param array $rules 验证规则
     * @param array $messages 错误消息
     * @param array $customAttributes 自定义属性名
     * @return array
     */
    protected function validateRequest(
        Request $request,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): array {
        $validator = Validator::make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $firstError = $errors->first();
            
            abort(422, $firstError);
        }

        return $validator->validated();
    }

    /**
     * 获取分页参数
     *
     * @param Request $request 请求
     * @param int $defaultPerPage 默认每页数量
     * @return array ['page' => 页码, 'per_page' => 每页数量]
     */
    protected function getPaginationParams(Request $request, int $defaultPerPage = 15): array
    {
        return [
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', $defaultPerPage)
        ];
    }

    /**
     * 获取排序参数
     *
     * @param Request $request 请求
     * @param string $defaultField 默认排序字段
     * @param string $defaultDir 默认排序方向
     * @return array ['field' => 字段, 'dir' => 方向]
     */
    protected function getSortParams(
        Request $request,
        string $defaultField = 'created_at',
        string $defaultDir = 'desc'
    ): array {
        return [
            'field' => $request->input('sort_field', $defaultField),
            'dir' => $request->input('sort_dir', $defaultDir)
        ];
    }

    /**
     * 获取筛选参数
     *
     * @param Request $request 请求
     * @param array $allowedFields 允许筛选的字段
     * @return array
     */
    protected function getFilterParams(Request $request, array $allowedFields = []): array
    {
        $filters = [];
        $filterData = $request->input('filters', []);

        if (empty($allowedFields)) {
            return $filterData;
        }

        foreach ($filterData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /**
     * 获取当前用户ID
     *
     * @return int|null
     */
    protected function userId(): ?int
    {
        return auth()->id();
    }

    /**
     * 获取当前用户
     *
     * @return \App\Models\User|null
     */
    protected function user(): ?\App\Models\User
    {
        // 优先从JWT payload获取用户
        $payload = request()->attributes->get('jwt_payload');
        if ($payload && isset($payload['user_id'])) {
            return \App\Models\User::find($payload['user_id']);
        }
        
        // 回退到auth facade
        return auth()->user();
    }

    /**
     * 获取当前租户ID
     *
     * @return int|null
     */
    protected function tenantId(): ?int
    {
        return tenant_id();
    }

    /**
     * 判断当前用户是否为管理员
     *
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return is_admin();
    }

    /**
     * 判断当前用户是否有权限
     *
     * @param string $permission 权限标识
     * @return bool
     */
    protected function can(string $permission): bool
    {
        return can($permission);
    }

    /**
     * 记录操作日志
     *
     * @param string $action 操作名称
     * @param array $context 上下文
     * @return void
     */
    protected function logAction(string $action, array $context = []): void
    {
        $userId = $this->userId();
        $tenantId = $this->tenantId();
        
        \Log::info("Action: {$action}", array_merge([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip' => $this->getClientIp(),
            'user_agent' => request()->userAgent()
        ], $context));
    }

    /**
     * 获取客户端IP
     *
     * @return string
     */
    protected function getClientIp(): string
    {
        return request()->ip();
    }

    /**
     * 构建查询条件
     *
     * @param Request $request 请求
     * @param array $fieldMapping 字段映射 ['请求字段' => '数据库字段']
     * @return array
     */
    protected function buildQueryConditions(Request $request, array $fieldMapping = []): array
    {
        $conditions = [];
        
        foreach ($fieldMapping as $requestField => $dbField) {
            $value = $request->input($requestField);
            
            if ($value !== null && $value !== '') {
                $conditions[$dbField] = $value;
            }
        }
        
        return $conditions;
    }

    /**
     * 执行事务操作
     *
     * @param callable $callback 回调函数
     * @return mixed
     */
    protected function transaction(callable $callback)
    {
        return \DB::transaction($callback);
    }

    /**
     * 返回资源响应
     *
     * @param mixed $data 数据
     * @param string $resourceClass 资源类
     * @return JsonResponse
     */
    protected function resource($data, string $resourceClass): JsonResponse
    {
        if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $collection = $resourceClass::collection($data);
            
            return $this->success([
                'list' => $collection,
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                    'has_more_pages' => $data->hasMorePages()
                ]
            ]);
        }
        
        $resource = new $resourceClass($data);
        
        return $this->success($resource);
    }
}
