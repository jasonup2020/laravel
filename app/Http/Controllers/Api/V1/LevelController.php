<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\LevelResource;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 职级控制器
 * 
 * 处理职级管理相关操作，包括职级的增删改查、批量删除等
 * 
 * @method JsonResponse index(Request $request) 获取职级列表
 * @method JsonResponse store(Request $request) 创建职级
 * @method JsonResponse show(int $id) 获取职级详情
 * @method JsonResponse update(Request $request, int $id) 更新职级
 * @method JsonResponse destroy(int $id) 删除职级
 * @method JsonResponse batchDelete(Request $request) 批量删除职级
 */
class LevelController extends BaseController
{
    /**
     * 职级服务实例
     *
     * @var LevelService
     */
    protected LevelService $levelService;

    /**
     * 构造函数
     *
     * @param LevelService $levelService 职级服务
     */
    public function __construct(LevelService $levelService)
    {
        $this->levelService = $levelService;
    }

    /**
     * 获取职级列表
     *
     * 分页获取职级列表，支持按名称、代码、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: 高级工程师
     * @queryParam code string 按代码筛选 Example: SENIOR
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
     *         "name": "高级工程师",
     *         "code": "SENIOR",
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

        $items = $this->levelService->getList(
            $filters,
            ['*'],
            [],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($items, LevelResource::class);
    }

    /**
     * 创建职级
     *
     * 创建新职级
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 职级名称 Example: 高级工程师
     * @bodyParam code string required 职级代码，必须唯一 Example: SENIOR
     * @bodyParam description string 可选 职级描述 Example: 高级技术职级
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "高级工程师",
     *     "code": "SENIOR"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:levels,code',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->levelService->create($data);

        return $this->success(new LevelResource($item), __('messages.create_success'));
    }

    /**
     * 获取职级详情
     *
     * 根据ID获取职级详细信息
     *
     * @param int $id 职级ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 职级ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "高级工程师",
     *     "code": "SENIOR",
     *     "status": 1
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->levelService->getByIdOrFail($id);

        return $this->success(new LevelResource($item));
    }

    /**
     * 更新职级
     *
     * 更新指定职级的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 职级ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 职级ID Example: 1
     * @bodyParam name string 可选 职级名称 Example: 资深工程师
     * @bodyParam code string 可选 职级代码，必须唯一 Example: PRINCIPAL
     * @bodyParam description string 可选 职级描述 Example: 资深技术职级
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "资深工程师",
     *     "code": "PRINCIPAL"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:levels,code,' . $id,
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
        ], [], [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'description' => __('validation.attributes.description'),
            'status' => __('validation.attributes.status'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->all();
        $item = $this->levelService->update($id, $data);

        return $this->success(new LevelResource($item), __('messages.update_success'));
    }

    /**
     * 删除职级
     *
     * 删除指定职级
     *
     * @param int $id 职级ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 职级ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->levelService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 批量删除职级
     *
     * 批量删除多个职级
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam ids array required 职级ID数组 Example: [1, 2, 3]
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
            'ids.*' => 'integer|exists:levels,id',
        ], [], [
            'ids' => __('validation.attributes.ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $ids = $request->input('ids', []);
        $count = $this->levelService->batchDelete($ids);

        return $this->success(['count' => $count], __('messages.delete_success'));
    }
}
