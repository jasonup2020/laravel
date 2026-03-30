<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 权限控制器
 * 
 * 处理权限管理相关操作，包括权限的增删改查、批量删除等
 * 
 * @method JsonResponse index(Request $request) 获取权限列表
 * @method JsonResponse store(Request $request) 创建权限
 * @method JsonResponse show(int $id) 获取权限详情
 * @method JsonResponse update(Request $request, int $id) 更新权限
 * @method JsonResponse destroy(int $id) 删除权限
 * @method JsonResponse batchDelete(Request $request) 批量删除权限
 */
class PermissionController extends BaseController
{
    /**
     * 权限服务实例
     *
     * @var PermissionService
     */
    protected PermissionService $permissionService;

    /**
     * 构造函数
     *
     * @param PermissionService $permissionService 权限服务
     */
    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * 获取权限列表
     *
     * 分页获取权限列表，支持按名称、标识、模块、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: 用户管理
     * @queryParam slug string 按标识筛选 Example: user.manage
     * @queryParam module string 按模块筛选 Example: user
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
     *         "name": "用户管理",
     *         "slug": "user.manage",
     *         "module": "user",
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
        $filters = $this->getFilterParams($request, ['name', 'slug', 'module', 'status']);
        $sort = $this->getSortParams($request);
        $perPage = $request->input('per_page', 15);

        $items = $this->permissionService->getList(
            $filters,
            ['*'],
            [],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($items, PermissionResource::class);
    }

    /**
     * 创建权限
     *
     * 创建新权限
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 权限名称 Example: 用户管理
     * @bodyParam slug string required 权限标识，必须唯一 Example: user.manage
     * @bodyParam module string 可选 所属模块 Example: user
     * @bodyParam description string 可选 权限描述 Example: 用户管理权限
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "用户管理",
     *     "slug": "user.manage",
     *     "module": "user"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:permissions,slug',
            'module' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'slug' => __('validation.attributes.slug'),
            'module' => __('validation.attributes.module'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->permissionService->create($data);

        return $this->success(new PermissionResource($item), __('messages.create_success'));
    }

    /**
     * 获取权限详情
     *
     * 根据ID获取权限详细信息
     *
     * @param int $id 权限ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 权限ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "用户管理",
     *     "slug": "user.manage",
     *     "module": "user",
     *     "status": 1
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->permissionService->getByIdOrFail($id);

        return $this->success(new PermissionResource($item));
    }

    /**
     * 更新权限
     *
     * 更新指定权限的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 权限ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 权限ID Example: 1
     * @bodyParam name string 可选 权限名称 Example: 新用户管理
     * @bodyParam slug string 可选 权限标识，必须唯一 Example: user.manage.new
     * @bodyParam module string 可选 所属模块 Example: user
     * @bodyParam description string 可选 权限描述 Example: 新用户管理权限
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "新用户管理",
     *     "slug": "user.manage.new"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:permissions,slug,' . $id,
            'module' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'slug' => __('validation.attributes.slug'),
            'module' => __('validation.attributes.module'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->permissionService->update($id, $data);

        return $this->success(new PermissionResource($item), __('messages.update_success'));
    }

    /**
     * 删除权限
     *
     * 删除指定权限
     *
     * @param int $id 权限ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 权限ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->permissionService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 批量删除权限
     *
     * 批量删除多个权限
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam ids array required 权限ID数组 Example: [1, 2, 3]
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": {
     *     "count": 3
     *   }
     * }
     */
    public function batchDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:permissions,id',
        ], [], [
            'ids' => __('validation.attributes.ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $ids = $request->input('ids', []);
        $count = $this->permissionService->batchDelete($ids);

        return $this->success(['count' => $count], __('messages.delete_success'));
    }
}
