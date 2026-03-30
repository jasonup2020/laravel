<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 用户控制器
 * 
 * 处理用户管理相关操作，包括用户的增删改查、启用禁用、角色分配等
 * 
 * @method JsonResponse index(Request $request) 获取用户列表
 * @method JsonResponse store(Request $request) 创建用户
 * @method JsonResponse show(int $id) 获取用户详情
 * @method JsonResponse update(Request $request, int $id) 更新用户
 * @method JsonResponse destroy(int $id) 删除用户
 * @method JsonResponse enable(int $id) 启用用户
 * @method JsonResponse disable(int $id) 禁用用户
 * @method JsonResponse assignRoles(Request $request, int $id) 分配角色
 */
class UserController extends BaseController
{
    /**
     * 用户服务实例
     *
     * @var UserService
     */
    protected UserService $userService;

    /**
     * 构造函数
     *
     * @param UserService $userService 用户服务
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * 获取用户列表
     *
     * 分页获取用户列表，支持按名称、邮箱、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: admin
     * @queryParam email string 按邮箱筛选 Example: admin@example.com
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
     *         "name": "admin",
     *         "email": "admin@example.com",
     *         "status": 1,
     *         "roles": [],
     *         "department": null,
     *         "position": null
     *       }
     *     ],
     *     "total": 1,
     *     "per_page": 15
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->getFilterParams($request, ['name', 'email', 'status']);
        $sort = $this->getSortParams($request);
        $perPage = $request->input('per_page', 15);

        $items = $this->userService->getList(
            $filters,
            ['*'],
            ['roles', 'department', 'position'],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->success($items);
    }

    /**
     * 创建用户
     *
     * 创建新用户账户
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 用户名称 Example: testuser
     * @bodyParam email string required 用户邮箱地址，必须唯一 Example: test@example.com
     * @bodyParam password string required 用户密码，最少6个字符 Example: password123
     * @bodyParam phone string 可选 用户手机号，必须唯一 Example: 13800138000
     * @bodyParam department_id int 可选 部门ID Example: 1
     * @bodyParam position_id int 可选 岗位ID Example: 1
     * @bodyParam level_id int 可选 职级ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "testuser",
     *     "email": "test@example.com"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20|unique:users',
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'level_id' => 'nullable|integer|exists:levels,id',
        ], [], [
            'name' => __('validation.attributes.name'),
            'email' => __('validation.attributes.email'),
            'password' => __('validation.attributes.password'),
            'phone' => __('validation.attributes.phone'),
            'department_id' => __('validation.attributes.department_id'),
            'position_id' => __('validation.attributes.position_id'),
            'level_id' => __('validation.attributes.level_id'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->userService->create($data);

        return $this->success($item, __('messages.create_success'));
    }

    /**
     * 获取用户详情
     *
     * 根据ID获取用户详细信息
     *
     * @param int $id 用户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 用户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "email": "admin@example.com",
     *     "status": 1,
     *     "roles": [],
     *     "department": null,
     *     "position": null
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->userService->getByIdOrFail($id);

        return $this->success($item);
    }

    /**
     * 更新用户
     *
     * 更新指定用户的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 用户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 用户ID Example: 1
     * @bodyParam name string 可选 用户名称 Example: newname
     * @bodyParam email string 可选 用户邮箱地址，必须唯一 Example: new@example.com
     * @bodyParam phone string 可选 用户手机号，必须唯一 Example: 13900139000
     * @bodyParam department_id int 可选 部门ID Example: 1
     * @bodyParam position_id int 可选 岗位ID Example: 1
     * @bodyParam level_id int 可选 职级ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "newname",
     *     "email": "new@example.com"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20|unique:users,phone,' . $id,
            'department_id' => 'nullable|integer|exists:departments,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'level_id' => 'nullable|integer|exists:levels,id',
        ], [], [
            'name' => __('validation.attributes.name'),
            'email' => __('validation.attributes.email'),
            'phone' => __('validation.attributes.phone'),
            'department_id' => __('validation.attributes.department_id'),
            'position_id' => __('validation.attributes.position_id'),
            'level_id' => __('validation.attributes.level_id'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->userService->update($id, $data);

        return $this->success($item, __('messages.update_success'));
    }

    /**
     * 删除用户
     *
     * 删除指定用户
     *
     * @param int $id 用户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 用户ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 启用用户
     *
     * 将指定用户状态设置为启用
     *
     * @param int $id 用户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 用户ID Example: 1
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
        $item = $this->userService->update($id, ['status' => 1]);

        return $this->success($item, __('messages.update_success'));
    }

    /**
     * 禁用用户
     *
     * 将指定用户状态设置为禁用
     *
     * @param int $id 用户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 用户ID Example: 1
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
        $item = $this->userService->update($id, ['status' => 0]);

        return $this->success($item, __('messages.update_success'));
    }

    /**
     * 分配角色
     *
     * 为指定用户分配角色
     *
     * @param Request $request HTTP请求对象
     * @param int $id 用户ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 用户ID Example: 1
     * @bodyParam role_ids array required 角色ID数组 Example: [1, 2]
     * 
     * @response {
     *   "code": 200,
     *   "message": "分配成功",
     *   "data": {
     *     "id": 1,
     *     "roles": [
     *       {"id": 1, "name": "admin"},
     *       {"id": 2, "name": "editor"}
     *     ]
     *   }
     * }
     */
    public function assignRoles(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ], [], [
            'role_ids' => __('validation.attributes.role_ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $roleIds = $request->input('role_ids', []);
        $user = $this->userService->getByIdOrFail($id);
        $user->roles()->sync($roleIds);

        return $this->success($user, __('messages.assign_success'));
    }
}
