<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\MenuResource;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 菜单控制器
 * 
 * 处理菜单管理相关操作，包括菜单的增删改查、树形结构、用户菜单、批量删除等
 * 
 * @method JsonResponse index(Request $request) 获取菜单列表
 * @method JsonResponse tree(Request $request) 获取菜单树形结构
 * @method JsonResponse userMenus(Request $request) 获取当前用户菜单
 * @method JsonResponse store(Request $request) 创建菜单
 * @method JsonResponse show(int $id) 获取菜单详情
 * @method JsonResponse update(Request $request, int $id) 更新菜单
 * @method JsonResponse destroy(int $id) 删除菜单
 * @method JsonResponse batchDelete(Request $request) 批量删除菜单
 */
class MenuController extends BaseController
{
    /**
     * 菜单服务实例
     *
     * @var MenuService
     */
    protected MenuService $menuService;

    /**
     * 构造函数
     *
     * @param MenuService $menuService 菜单服务
     */
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * 获取菜单列表
     *
     * 分页获取菜单列表，支持按名称、标识、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: 用户管理
     * @queryParam slug string 按标识筛选 Example: user
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
     *         "slug": "user",
     *         "status": 1,
     *         "parent": null,
     *         "children": []
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

        $items = $this->menuService->getList(
            $filters,
            ['*'],
            ['parent', 'children'],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($items, MenuResource::class);
    }

    /**
     * 获取菜单树形结构
     *
     * 获取所有菜单的树形结构数据
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "系统管理",
     *       "slug": "system",
     *       "children": [
     *         {
     *           "id": 2,
     *           "name": "用户管理",
     *           "slug": "user"
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function tree(Request $request): JsonResponse
    {
        $items = $this->menuService->getAll([], ['*'], ['children']);

        return $this->success(MenuResource::collection($items));
    }

    /**
     * 获取当前用户菜单
     *
     * 获取当前登录用户有权访问的菜单列表
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "系统管理",
     *       "slug": "system",
     *       "children": [
     *         {
     *           "id": 2,
     *           "name": "用户管理",
     *           "slug": "user"
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function userMenus(Request $request): JsonResponse
    {
        $user = $this->user();
        $items = $this->menuService->getUserMenus($user);

        return $this->success(MenuResource::collection($items));
    }

    /**
     * 创建菜单
     *
     * 创建新菜单
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 菜单名称 Example: 用户管理
     * @bodyParam slug string required 菜单标识，必须唯一 Example: user
     * @bodyParam parent_id int 可选 父级菜单ID Example: 1
     * @bodyParam icon string 可选 菜单图标 Example: user
     * @bodyParam path string 可选 路由路径 Example: /user
     * @bodyParam component string 可选 组件路径 Example: user/index
     * @bodyParam sort int 可选 排序 Example: 1
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "用户管理",
     *     "slug": "user"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:menus,slug',
            'parent_id' => 'nullable|integer|exists:menus,id',
            'icon' => 'nullable|string|max:100',
            'path' => 'nullable|string|max:255',
            'component' => 'nullable|string|max:255',
            'sort' => 'nullable|integer',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'slug' => __('validation.attributes.slug'),
            'parent_id' => __('validation.attributes.parent_id'),
            'icon' => __('validation.attributes.icon'),
            'path' => __('validation.attributes.path'),
            'component' => __('validation.attributes.component'),
            'sort' => __('validation.attributes.sort'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->menuService->create($data);

        return $this->success(new MenuResource($item), __('messages.create_success'));
    }

    /**
     * 获取菜单详情
     *
     * 根据ID获取菜单详细信息
     *
     * @param int $id 菜单ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 菜单ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "用户管理",
     *     "slug": "user",
     *     "status": 1,
     *     "parent": null,
     *     "children": []
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->menuService->getByIdOrFail($id, ['*'], ['parent', 'children']);

        return $this->success(new MenuResource($item));
    }

    /**
     * 更新菜单
     *
     * 更新指定菜单的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 菜单ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 菜单ID Example: 1
     * @bodyParam name string 可选 菜单名称 Example: 新用户管理
     * @bodyParam slug string 可选 菜单标识，必须唯一 Example: new_user
     * @bodyParam parent_id int 可选 父级菜单ID Example: 1
     * @bodyParam icon string 可选 菜单图标 Example: user-new
     * @bodyParam path string 可选 路由路径 Example: /new-user
     * @bodyParam component string 可选 组件路径 Example: user/new-index
     * @bodyParam sort int 可选 排序 Example: 2
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "新用户管理",
     *     "slug": "new_user"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:menus,slug,' . $id,
            'parent_id' => 'nullable|integer|exists:menus,id',
            'icon' => 'nullable|string|max:100',
            'path' => 'nullable|string|max:255',
            'component' => 'nullable|string|max:255',
            'sort' => 'nullable|integer',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'slug' => __('validation.attributes.slug'),
            'parent_id' => __('validation.attributes.parent_id'),
            'icon' => __('validation.attributes.icon'),
            'path' => __('validation.attributes.path'),
            'component' => __('validation.attributes.component'),
            'sort' => __('validation.attributes.sort'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->menuService->update($id, $data);

        return $this->success(new MenuResource($item), __('messages.update_success'));
    }

    /**
     * 删除菜单
     *
     * 删除指定菜单
     *
     * @param int $id 菜单ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 菜单ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->menuService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 批量删除菜单
     *
     * 批量删除多个菜单
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam ids array required 菜单ID数组 Example: [1, 2, 3]
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
            'ids.*' => 'integer|exists:menus,id',
        ], [], [
            'ids' => __('validation.attributes.ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $ids = $request->input('ids', []);
        $count = $this->menuService->batchDelete($ids);

        return $this->success(['count' => $count], __('messages.delete_success'));
    }
}
