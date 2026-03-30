<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\DepartmentResource;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 部门控制器
 * 
 * 处理部门管理相关操作，包括部门的增删改查、树形结构、批量删除等
 * 
 * @method JsonResponse index(Request $request) 获取部门列表
 * @method JsonResponse tree(Request $request) 获取部门树形结构
 * @method JsonResponse store(Request $request) 创建部门
 * @method JsonResponse show(int $id) 获取部门详情
 * @method JsonResponse update(Request $request, int $id) 更新部门
 * @method JsonResponse destroy(int $id) 删除部门
 * @method JsonResponse batchDelete(Request $request) 批量删除部门
 */
class DepartmentController extends BaseController
{
    /**
     * 部门服务实例
     *
     * @var DepartmentService
     */
    protected DepartmentService $departmentService;

    /**
     * 构造函数
     *
     * @param DepartmentService $departmentService 部门服务
     */
    public function __construct(DepartmentService $departmentService)
    {
        $this->departmentService = $departmentService;
    }

    /**
     * 获取部门列表
     *
     * 分页获取部门列表，支持按名称、代码、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: 技术部
     * @queryParam code string 按代码筛选 Example: TECH
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
     *         "name": "技术部",
     *         "code": "TECH",
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
        $filters = $this->getFilterParams($request, ['name', 'code', 'status']);
        $sort = $this->getSortParams($request);
        $perPage = $request->input('per_page', 15);

        $items = $this->departmentService->getList(
            $filters,
            ['*'],
            ['parent', 'children'],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($items, DepartmentResource::class);
    }

    /**
     * 获取部门树形结构
     *
     * 获取所有部门的树形结构数据
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
     *       "name": "总公司",
     *       "code": "HQ",
     *       "children": [
     *         {
     *           "id": 2,
     *           "name": "技术部",
     *           "code": "TECH"
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function tree(Request $request): JsonResponse
    {
        $items = $this->departmentService->getAll([], ['*'], ['children']);

        return $this->success(DepartmentResource::collection($items));
    }

    /**
     * 创建部门
     *
     * 创建新部门
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 部门名称 Example: 技术部
     * @bodyParam code string required 部门代码，必须唯一 Example: TECH
     * @bodyParam parent_id int 可选 父级部门ID Example: 1
     * @bodyParam description string 可选 部门描述 Example: 技术研发部门
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "技术部",
     *     "code": "TECH"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:departments,code',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'parent_id' => __('validation.attributes.parent_id'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->departmentService->create($data);

        return $this->success(new DepartmentResource($item), __('messages.create_success'));
    }

    /**
     * 获取部门详情
     *
     * 根据ID获取部门详细信息
     *
     * @param int $id 部门ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 部门ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "技术部",
     *     "code": "TECH",
     *     "status": 1,
     *     "parent": null,
     *     "children": []
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->departmentService->getByIdOrFail($id, ['*'], ['parent', 'children']);

        return $this->success(new DepartmentResource($item));
    }

    /**
     * 更新部门
     *
     * 更新指定部门的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 部门ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 部门ID Example: 1
     * @bodyParam name string 可选 部门名称 Example: 新技术部
     * @bodyParam code string 可选 部门代码，必须唯一 Example: NEW_TECH
     * @bodyParam parent_id int 可选 父级部门ID Example: 1
     * @bodyParam description string 可选 部门描述 Example: 新技术研发部门
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "新技术部",
     *     "code": "NEW_TECH"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:departments,code,' . $id,
            'parent_id' => 'nullable|integer|exists:departments,id',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'parent_id' => __('validation.attributes.parent_id'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->departmentService->update($id, $data);

        return $this->success(new DepartmentResource($item), __('messages.update_success'));
    }

    /**
     * 删除部门
     *
     * 删除指定部门
     *
     * @param int $id 部门ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 部门ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->departmentService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 批量删除部门
     *
     * 批量删除多个部门
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam ids array required 部门ID数组 Example: [1, 2, 3]
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
            'ids.*' => 'integer|exists:departments,id',
        ], [], [
            'ids' => __('validation.attributes.ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $ids = $request->input('ids', []);
        $count = $this->departmentService->batchDelete($ids);

        return $this->success(['count' => $count], __('messages.delete_success'));
    }
}
