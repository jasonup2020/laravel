<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\BusinessException;

/**
 * 基础服务类
 * 
 * 所有服务类都应继承此类，提供通用的CRUD操作：
 * - 列表查询（支持分页、筛选、排序）
 * - 详情查询
 * - 创建记录
 * - 更新记录
 * - 删除记录
 * - 批量操作
 * 
 * @package App\Services
 * @author  SaaS Platform
 * @version 1.0.0
 */
abstract class BaseService
{
    /**
     * 模型类名
     *
     * @var string
     */
    protected string $modelClass;

    /**
     * 模型实例
     *
     * @var Model
     */
    protected Model $model;

    /**
     * 构造函数
     */
    public function __construct()
    {
        if (isset($this->modelClass) && $this->modelClass) {
            $this->model = new $this->modelClass();
        }
    }

    /**
     * 获取列表（支持分页）
     *
     * @param array $filters 筛选条件
     * @param array $columns 查询字段
     * @param array $relations 关联关系
     * @param int $perPage 每页数量
     * @param string $orderBy 排序字段
     * @param string $orderDir 排序方向
     * @return LengthAwarePaginator
     */
    public function getList(
        array $filters = [],
        array $columns = ['*'],
        array $relations = [],
        int $perPage = 15,
        string $orderBy = 'created_at',
        string $orderDir = 'desc'
    ): LengthAwarePaginator {
        $query = $this->model->newQuery();

        // 加载关联关系
        if (!empty($relations)) {
            $query->with($relations);
        }

        // 应用筛选条件
        $this->applyFilters($query, $filters);

        // 排序
        $query->orderBy($orderBy, $orderDir);

        // 分页
        return $query->paginate($perPage, $columns);
    }

    /**
     * 获取所有记录（不分页）
     *
     * @param array $filters 筛选条件
     * @param array $columns 查询字段
     * @param array $relations 关联关系
     * @return Collection
     */
    public function getAll(
        array $filters = [],
        array $columns = ['*'],
        array $relations = []
    ): Collection {
        $query = $this->model->newQuery();

        // 加载关联关系
        if (!empty($relations)) {
            $query->with($relations);
        }

        // 应用筛选条件
        $this->applyFilters($query, $filters);

        return $query->get($columns);
    }

    /**
     * 根据ID获取详情
     *
     * @param int $id ID
     * @param array $columns 查询字段
     * @param array $relations 关联关系
     * @return Model|null
     */
    public function getById(int $id, array $columns = ['*'], array $relations = []): ?Model
    {
        $query = $this->model->newQuery();

        // 加载关联关系
        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id, $columns);
    }

    /**
     * 根据ID获取详情（失败抛出异常）
     *
     * @param int $id ID
     * @param array $columns 查询字段
     * @param array $relations 关联关系
     * @return Model
     * @throws BusinessException
     */
    public function getByIdOrFail(int $id, array $columns = ['*'], array $relations = []): Model
    {
        $model = $this->getById($id, $columns, $relations);

        if (!$model) {
            throw new BusinessException(__('messages.not_found'), 404);
        }

        return $model;
    }

    /**
     * 根据条件获取单条记录
     *
     * @param array $conditions 条件
     * @param array $columns 查询字段
     * @param array $relations 关联关系
     * @return Model|null
     */
    public function getOneByConditions(array $conditions, array $columns = ['*'], array $relations = []): ?Model
    {
        $query = $this->model->newQuery();

        // 加载关联关系
        if (!empty($relations)) {
            $query->with($relations);
        }

        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->first($columns);
    }

    /**
     * 创建记录
     *
     * @param array $data 数据
     * @return Model
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $model = $this->model->create($data);
            
            // 创建后处理
            $this->afterCreate($model, $data);
            
            return $model;
        });
    }

    /**
     * 批量创建
     *
     * @param array $dataList 数据列表
     * @return Collection
     */
    public function batchCreate(array $dataList): Collection
    {
        return DB::transaction(function () use ($dataList) {
            $models = collect();
            
            foreach ($dataList as $data) {
                $models->push($this->create($data));
            }
            
            return $models;
        });
    }

    /**
     * 更新记录
     *
     * @param int $id ID
     * @param array $data 数据
     * @return Model
     * @throws BusinessException
     */
    public function update(int $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            $model = $this->getByIdOrFail($id);
            
            $model->update($data);
            
            // 更新后处理
            $this->afterUpdate($model, $data);
            
            return $model;
        });
    }

    /**
     * 批量更新
     *
     * @param array $ids ID数组
     * @param array $data 数据
     * @return int
     */
    public function batchUpdate(array $ids, array $data): int
    {
        return DB::transaction(function () use ($ids, $data) {
            return $this->model->whereIn('id', $ids)->update($data);
        });
    }

    /**
     * 删除记录
     *
     * @param int $id ID
     * @return bool
     * @throws BusinessException
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $model = $this->getByIdOrFail($id);
            
            // 删除前处理
            $this->beforeDelete($model);
            
            return $model->delete();
        });
    }

    /**
     * 批量删除
     *
     * @param array $ids ID数组
     * @return int
     */
    public function batchDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->model->whereIn('id', $ids)->delete();
        });
    }

    /**
     * 强制删除记录
     *
     * @param int $id ID
     * @return bool
     * @throws BusinessException
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $model = $this->getByIdOrFail($id);
            
            return $model->forceDelete();
        });
    }

    /**
     * 恢复删除的记录
     *
     * @param int $id ID
     * @return bool
     * @throws BusinessException
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $model = $this->model->onlyTrashed()->find($id);
            
            if (!$model) {
                throw new BusinessException(__('messages.not_found'), 404);
            }
            
            return $model->restore();
        });
    }

    /**
     * 切换状态
     *
     * @param int $id ID
     * @return Model
     * @throws BusinessException
     */
    public function toggleStatus(int $id): Model
    {
        $model = $this->getByIdOrFail($id);
        
        $model->status = $model->status ? 0 : 1;
        $model->save();
        
        return $model;
    }

    /**
     * 检查是否存在
     *
     * @param array $conditions 条件
     * @return bool
     */
    public function exists(array $conditions): bool
    {
        $query = $this->model->newQuery();
        
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        
        return $query->exists();
    }

    /**
     * 统计数量
     *
     * @param array $conditions 条件
     * @return int
     */
    public function count(array $conditions = []): int
    {
        $query = $this->model->newQuery();
        
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        
        return $query->count();
    }

    /**
     * 应用筛选条件
     *
     * @param mixed $query 查询构建器
     * @param array $filters 筛选条件
     * @return void
     */
    protected function applyFilters($query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // 处理特殊操作符
            if (is_array($value) && isset($value['operator'])) {
                $operator = $value['operator'];
                $val = $value['value'];

                switch ($operator) {
                    case 'like':
                        $query->where($field, 'like', "%{$val}%");
                        break;
                    case 'start_with':
                        $query->where($field, 'like', "{$val}%");
                        break;
                    case 'end_with':
                        $query->where($field, 'like', "%{$val}");
                        break;
                    case 'in':
                        $query->whereIn($field, (array) $val);
                        break;
                    case 'not_in':
                        $query->whereNotIn($field, (array) $val);
                        break;
                    case 'between':
                        $query->whereBetween($field, (array) $val);
                        break;
                    case 'not_between':
                        $query->whereNotBetween($field, (array) $val);
                        break;
                    case 'null':
                        $query->whereNull($field);
                        break;
                    case 'not_null':
                        $query->whereNotNull($field);
                        break;
                    default:
                        $query->where($field, $operator, $val);
                }
            } else {
                // 默认等于
                $query->where($field, $value);
            }
        }
    }

    /**
     * 创建后处理（子类可重写）
     *
     * @param Model $model 模型
     * @param array $data 数据
     * @return void
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // 子类可重写此方法
    }

    /**
     * 更新后处理（子类可重写）
     *
     * @param Model $model 模型
     * @param array $data 数据
     * @return void
     */
    protected function afterUpdate(Model $model, array $data): void
    {
        // 子类可重写此方法
    }

    /**
     * 删除前处理（子类可重写）
     *
     * @param Model $model 模型
     * @return void
     */
    protected function beforeDelete(Model $model): void
    {
        // 子类可重写此方法
    }

    /**
     * 记录日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     * @return void
     */
    protected function log(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }
}
