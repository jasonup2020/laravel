<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Resources\DeviceResource;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 设备控制器
 * 
 * 处理设备管理相关操作，包括设备列表、设备详情、设备删除、设备登出等
 * 
 * @method JsonResponse index(Request $request) 获取设备列表
 * @method JsonResponse show(int $id) 获取设备详情
 * @method JsonResponse destroy(int $id) 删除设备
 * @method JsonResponse logout(int $id) 登出指定设备
 * @method JsonResponse logoutAll(Request $request) 登出所有设备
 */
class DeviceController extends BaseController
{
    /**
     * 设备服务实例
     *
     * @var DeviceService
     */
    protected DeviceService $deviceService;

    /**
     * 构造函数
     *
     * @param DeviceService $deviceService 设备服务
     */
    public function __construct(DeviceService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * 获取设备列表
     *
     * 分页获取设备列表，支持按设备ID、设备名称、设备类型、状态筛选
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @queryParam page int 页码 Example: 1
     * @queryParam per_page int 每页数量 Example: 15
     * @queryParam device_id string 按设备ID筛选 Example: device-001
     * @queryParam device_name string 按设备名称筛选 Example: iPhone 15
     * @queryParam device_type string 按设备类型筛选 Example: mobile
     * @queryParam status int 按状态筛选 (0:离线, 1:在线) Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "device_id": "device-001",
     *         "device_name": "iPhone 15",
     *         "device_type": "mobile",
     *         "status": 1,
     *         "user": {
     *           "id": 1,
     *           "name": "admin"
     *         }
     *       }
     *     ],
     *     "total": 1,
     *     "per_page": 15
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->getFilterParams($request, ['device_id', 'device_name', 'device_type', 'status']);
        $sort = $this->getSortParams($request);
        $perPage = $request->input('per_page', 15);

        $items = $this->deviceService->getList(
            $filters,
            ['*'],
            ['user'],
            $perPage,
            $sort['field'],
            $sort['dir']
        );

        return $this->resource($items, DeviceResource::class);
    }

    /**
     * 获取设备详情
     *
     * 根据ID获取设备详细信息
     *
     * @param int $id 设备ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 设备ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "device_id": "device-001",
     *     "device_name": "iPhone 15",
     *     "device_type": "mobile",
     *     "status": 1,
     *     "user": {
     *       "id": 1,
     *       "name": "admin"
     *     }
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->deviceService->getByIdOrFail($id, ['*'], ['user']);

        return $this->success(new DeviceResource($item));
    }

    /**
     * 删除设备
     *
     * 删除指定设备记录
     *
     * @param int $id 设备ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 设备ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "删除成功",
     *   "data": null
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $this->deviceService->delete($id);

        return $this->success(null, __('messages.delete_success'));
    }

    /**
     * 登出指定设备
     *
     * 使指定设备的登录令牌失效，强制登出该设备
     *
     * @param int $id 设备ID
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @urlParam id int required 设备ID Example: 1
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": null
     * }
     */
    public function logout(int $id): JsonResponse
    {
        $this->deviceService->logout($id);

        return $this->success(null, __('messages.success'));
    }

    /**
     * 登出所有设备
     *
     * 使当前用户所有设备的登录令牌失效，强制登出所有设备
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "count": 5
     *   }
     * }
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $userId = $this->userId();
        $count = $this->deviceService->logoutAll($userId);

        return $this->success(['count' => $count], __('messages.success'));
    }
}
