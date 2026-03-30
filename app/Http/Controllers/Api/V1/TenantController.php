<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 租户控制器
 * 
 * 处理租户管理相关操作，包括租户的增删改查、启用禁用、配置管理等
 * 
 * @method JsonResponse index(Request $request) 获取租户列表
 * @method JsonResponse store(Request $request) 创建租户
 * @method JsonResponse show(int $id) 获取租户详情
 * @method JsonResponse update(Request $request, int $id) 更新租户
 * @method JsonResponse destroy(int $id) 删除租户
 * @method JsonResponse enable(int $id) 启用租户
 * @method JsonResponse disable(int $id) 禁用租户
 * @method JsonResponse config(Request $request, int $id) 更新租户配置
 */
class TenantController extends BaseController
{
    /**
     * 租户服务实例
     *
     * @var TenantService
     */
    protected TenantService $tenantService;

    /**
     * 构造函数
     *
     * @param TenantService $tenantService 租户服务
     */
    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * 获取租户列表
     *
     * 分页获取租户列表，支持按名称、代码、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: tenant1
     * @queryParam code string 按代码筛选 Example: T001
     * @queryParam status int 按状态筛选 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "tenant1",
     *         "code": "T001",
     *         "domain": "tenant1.example.com",
     *         "status": 1
     *       }
     *     ],
     *     "total": 1,
     *     "per_page": 15
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->getFilterParams($request, ['name', 'code', 'status']);
        $sort = $this->getSortParams($request);
        $perPage = $request->input('per_page', 15);

        $items = $this->tenantService->getList(
            $filters,
            ['*'],
            [],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->success($items);
    }

    /**
     * 创建租户
     *
     * 创建新租户
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 租户名称 Example: tenant1
     * @bodyParam code string required 租户代码，必须唯一 Example: T001
     * @bodyParam domain string 可选 租户域名 Example: tenant1.example.com
     * @bodyParam database string 可选 租户数据库 Example: tenant_t001
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "tenant1",
     *     "code": "T001",
     *     "domain": "tenant1.example.com"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:tenants,code',
            'domain' => 'nullable|string|max:255',
            'database' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'domain' => __('validation.attributes.domain'),
            'database' => __('validation.attributes.database'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->tenantService->create($data);

        return $this->success($item, __('messages.create_success'));
    }

    /**
     * 获取租户详情
     *
     * 根据ID获取租户详细信息
     *
     * @param int $id 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 租户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "tenant1",
     *     "code": "T001",
     *     "domain": "tenant1.example.com",
     *     "status": 1,
     *     "config": {}
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->tenantService->getByIdOrFail($id);

        return $this->success($item);
    }

    /**
     * 更新租户
     *
     * 更新指定租户的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 租户ID Example: 1
     * @bodyParam name string 可选 租户名称 Example: newtenant
     * @bodyParam code string 可选 租户代码，必须唯一 Example: T002
     * @bodyParam domain string 可选 租户域名 Example: new.example.com
     * @bodyParam database string 可选 租户数据库 Example: tenant_t002
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "newtenant",
     *     "code": "T002"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:tenants,code,' . $id,
            'domain' => 'nullable|string|max:255',
            'database' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'domain' => __('validation.attributes.domain'),
            'database' => __('validation.attributes.database'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->tenantService->update($id, $data);

        return $this->success($item, __('messages.update_success'));
    }

    /**
     * 删除租户
     *
     * 删除指定租户
     *
     * @param int $id 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 租户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->tenantService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 启用租户
     *
     * 将指定租户状态设置为启用
     *
     * @param int $id 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 租户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "status": 1
     *   }
     * }
     */
    public function enable(int $id): JsonResponse
    {
        $item = $this->tenantService->update($id, ['status' => 1]);

        return $this->success($item, __('messages.update_success'));
    }

    /**
     * 禁用租户
     *
     * 将指定租户状态设置为禁用
     *
     * @param int $id 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 租户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "status": 0
     *   }
     * }
     */
    public function disable(int $id): JsonResponse
    {
        $item = $this->tenantService->update($id, ['status' => 0]);

        return $this->success($item, __('messages.update_success'));
    }

    /**
     * 更新租户配置
     *
     * 更新指定租户的配置信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 租户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 租户ID Example: 1
     * @bodyParam config array 可选 配置信息 Example: {"theme": "dark", "language": "zh"}
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "config": {
     *       "theme": "dark",
     *       "language": "zh"
     *     }
     *   }
     * }
     */
    public function config(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'config' => 'nullable|array',
        ], [], [
            'config' => __('validation.attributes.config'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $config = $request->input('config', []);
        $item = $this->tenantService->updateConfig($id, $config);

        return $this->success($item, __('messages.update_success'));
    }
}
