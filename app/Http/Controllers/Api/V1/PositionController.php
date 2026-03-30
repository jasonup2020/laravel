<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\PositionResource;
use App\Services\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 岗位控制器
 * 
 * 处理岗位管理相关操作，包括岗位的增删改查、批量删除等
 * 
 * @method JsonResponse index(['name', 'code', 'status']) 获取岗位列表
 * @method JsonResponse store(Request $request) 创建岗位
 * @method JsonResponse show(int $id) 获取岗位详情
 * @method JsonResponse update(Request $request, int $id) 更新岗位
 * @method JsonResponse destroy(int $id) 删除岗位
 * @method JsonResponse batchDelete(Request $request) 批量删除岗位
 */
class PositionController extends BaseController
{
    /**
     * 岗位服务实例
     *
     * @var PositionService
     */
    protected PositionService $positionService;

    /**
     * 构造函数
     *
     * @param PositionService $positionService 岗位服务
     */
    public function __construct(PositionService $positionService)
    {
        $this->positionService = $positionService;
    }

    /**
     * 获取岗位列表
     *
     * 分页获取岗位列表，支持按名称、代码、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam name string 按名称筛选 Example: 开发工程师
     * @queryParam code string 按代码筛选 Example: DEV
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
     *         "name": "开发工程师",
     *         "code": "DEV",
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

        $items = $this->positionService->getList(
            $filters,
            ['*'],
            [],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($items, PositionResource::class);
    }

    /**
     * 创建岗位
     *
     * 创建新岗位
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam name string required 岗位名称 Example: 开发工程师
     * @bodyParam code string required 岗位代码，必须唯一 Example: DEV
     * @bodyParam description string 可选 岗位描述 Example: 软件开发岗位
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "创建成功",
     *   "data": {
     *     "id": 1,
     *     "name": "开发工程师",
     *     "code": "DEV"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:positions,code',
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
        $item = $this->positionService->create($data);

        return $this->success(new PositionResource($item), __('messages.create_success'));
    }

    /**
     * 获取岗位详情
     *
     * 根据ID获取岗位详细信息
     *
     * @param int $id 岗位ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 岗位ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "开发工程师",
     *     "code": "DEV",
     *     "status": 1
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->positionService->getByIdOrFail($id);

        return $this->success(new PositionResource($item));
    }

    /**
     * 更新岗位
     *
     * 更新指定岗位的信息
     *
     * @param Request $request HTTP请求对象
     * @param int $id 岗位ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 岗位ID Example: 1
     * @bodyParam name string 可选 岗位名称 Example: 高级开发工程师
     * @bodyParam code string 可选 岗位代码，必须唯一 Example: SENIOR_DEV
     * @bodyParam description string 可选 岗位描述 Example: 高级软件开发岗位
     * @bodyParam status int 可选 状态 (0:禁用, 1:启用) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "更新成功",
     *   "data": {
     *     "id": 1,
     *     "name": "高级开发工程师",
     *     "code": "SENIOR_DEV"
     *   }
     * }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:positions,code,' . $id,
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
        $item = $this->positionService->update($id, $data);

        return $this->success(new PositionResource($item), __('messages.update_success'));
    }

    /**
     * 删除岗位
     *
     * 删除指定岗位
     *
     * @param int $id 岗位ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 岗位ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->positionService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 批量删除岗位
     *
     * 批量删除多个岗位
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam ids array required 岗位ID数组 Example: [1, 2, 3]
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
            'ids.*' => 'integer|exists:positions,id',
        ], [], [
            'ids' => __('validation.attributes.ids'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $ids = $request->input('ids', []);
        $count = $this->positionService->batchDelete($ids);

        return $this->success(['count' => $count], __('messages.delete_success'));
    }
}
