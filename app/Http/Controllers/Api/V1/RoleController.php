<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 角色控制器
 * 
 * 处理角色管理相关操作，包括角色的增删改查、权限分配、批量删除等
 * 
 * @method JsonResponse index(Request $request) 获取角色列表
 * @method JsonResponse store(Request $request) 创建角色
 * @method JsonResponse show(int $id) 获取角色详情
 * @method JsonResponse update(Request $request, int $id) 更新角色
 * @method JsonResponse destroy(int $id) 删除角色
 * @method JsonResponse assignPermissions(Request $request, int $id) 分配权限
 * @method JsonResponse batchDelete(Request $request) 批量删除角色
 */
class RoleController extends BaseController
{
    /**
     * 角色服务实例
     *
     * @var RoleService
     */
    protected RoleService $roleService;

    /**
     * 构造函数
     *
     * @param RoleService $roleService 角色服务
     */
    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * 获取角色列表
     *
     * 分页获取角色列表，支持按名称、标识、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: admin
     * @queryParam slug string 按标识筛选 Example: administrator
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
     *         "name": "管理员",
     *         "slug": "admin",
     *         "status": 1,
     *         "permissions": []
     *       }
     *     ],
     *     "total": 1,
     *     "per_page": 15
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->getFilterParams($request, ['name', 'slug', 'status']);
        $sort = $this->getSortParams($request);
        $perPage = $request->input('per_page', 15);

        $roles = $this->roleService->getList(
            $filters,
            ['*'],
            ['permissions'],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($roles, RoleResource::class);
    }

    /**
     * 创建角色
     *
     * 创建新角色
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 角色名称 Example: 管理员
     * @bodyParam slug string required 角色标识，必须唯一 Example: admin
     * @bodyParam description string 可选 角色描述 Example: 系统管理员角色
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "管理员",
     *     "slug": "admin"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:roles,slug',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'slug' => __('validation.attributes.slug'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $role = $this->roleService->create($data);

        return $this->success(new RoleResource($role), __('messages.create_success'));
    }

    /**
     * 获取角色详情
     *
     * 根据ID获取角色详细信息
     *
     * @param int $id 角色ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 角色ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "管理员",
     *     "slug": "admin",
     *     "status": 1,
     *     "permissions": []
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $role = $this->roleService->getByIdOrFail($id, ['*'], ['permissions']);

        return $this->success(new RoleResource($role));
    }

    /**
     * 更新角色
     *
     * 更新指定角色的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 角色ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 角色ID Example: 1
     * @bodyParam name string 可选 角色名称 Example: 新管理员
     * @bodyParam slug string 可选 角色标识，必须唯一 Example: new_admin
     * @bodyParam description string 可选 角色描述 Example: 新管理员角色
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "新管理员",
     *     "slug": "new_admin"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:roles,slug,' . $id,
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'slug' => __('validation.attributes.slug'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $role = $this->roleService->update($id, $data);

        return $this->success(new RoleResource($role), __('messages.update_success'));
    }

    /**
     * 删除角色
     *
     * 删除指定角色
     *
     * @param int $id 角色ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 角色ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->roleService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 分配权限
     *
     * 为指定角色分配权限
     *
     * @param Request $request HTTP请求对象
     * @param int $id 角色ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 角色ID Example: 1
     * @bodyParam permission_ids array required 权限ID数组 Example: [1, 2, 3]
     * 
     * @response {
     *   "code": 200,
     *   "message": "分配成功",
     *   "data": {
     *     "id": 1,
     *     "name": "管理员",
     *     "permissions": [
     *       {"id": 1, "name": "用户管理"},
     *       {"id": 2, "name": "角色管理"}
     *     ]
     *   }
     * }
     */
    public function assignPermissions(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ], [], [
            'permission_ids' => __('validation.attributes.permission_ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $permissionIds = $request->input('permission_ids', []);
        $role = $this->roleService->assignPermissions($id, $permissionIds);

        return $this->success(new RoleResource($role), __('messages.assign_success'));
    }

    /**
     * 批量删除角色
     *
     * 批量删除多个角色
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam ids array required 角色ID数组 Example: [1, 2, 3]
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
            'ids.*' => 'integer|exists:roles,id',
        ], [], [
            'ids' => __('validation.attributes.ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $ids = $request->input('ids', []);
        $count = $this->roleService->batchDelete($ids);

        return $this->success(['count' => $count], __('messages.delete_success'));
    }
}
