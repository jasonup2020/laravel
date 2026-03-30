 里的<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

/**
 * 基础模型 Trait
 * 提供通用的 CRUD 操作、软删除、租户隔离、审计字段等功能
 */
trait BaseModelTrait
{
    use SoftDeletes;

    /**
     * 是否启用租户隔离
     * @var bool
     */
    protected bool $tenantable = false;

    /**
     * 是否启用审计字段
     * @var bool
     */
    protected bool $auditable = false;

    /**
     * 租户 ID 字段名
     * @var string
     */
    protected string $tenantIdColumn = 'tenant_id';

    /**
     * 创建者字段名
     * @var string
     */
    protected string $createdByColumn = 'created_by';

    /**
     * 更新者字段名
     * @var string
     */
    protected string $updatedByColumn = 'updated_by';

    /**
     * 模型的"引导"方法
     */
    protected static function bootBaseModelTrait()
    {
        // 自动设置租户 ID
        static::creating(function ($model) {
            if ($model->tenantable && !isset($model->{$model->tenantIdColumn})) {
                if (Auth::check() && Auth::user()->{$model->tenantIdColumn}) {
                    $model->{$model->tenantIdColumn} = Auth::user()->{$model->tenantIdColumn};
                }
            }
        });

        // 自动设置创建者和更新者
        static::creating(function ($model) {
            if ($model->auditable && Auth::check()) {
                $model->{$model->createdByColumn} = Auth::id();
                $model->{$model->updatedByColumn} = Auth::id();
            }
        });

        static::updating(function ($model) {
            if ($model->auditable && Auth::check()) {
                $model->{$model->updatedByColumn} = Auth::id();
            }
        });

        // 全局租户隔离作用域
        static::addGlobalScope('tenant', function (Builder $builder) {
            $model = $builder->getModel();
            if ($model->tenantable && Auth::check()) {
                $tenantId = Auth::user()->{$model->tenantIdColumn} ?? null;
                if ($tenantId) {
                    $builder->where($model->tenantIdColumn, $tenantId);
                }
            }
        });
    }

    /**
     * 根据条件查询单条数据（查）
     * 
     * @param array $conditions
     * @param array $columns
     * @return static|null
     */
    public static function findByConditions(array $conditions, array $columns = ['*']): ?static
    {
        $query = static::query();
        
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
     * 根据条件查询多条数据（查）
     * 
     * @param array $conditions
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findAllByConditions(array $conditions, array $columns = ['*'])
    {
        $query = static::query();
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->get($columns);
    }

    /**
     * 批量创建（增）
     * 
     * @param array $dataList
     * @return array
     */
    public static function batchCreate(array $dataList): array
    {
        $items = [];
        foreach ($dataList as $data) {
            $items[] = static::create($data);
        }
        return $items;
    }

    /**
     * 根据ID更新（改）
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function updateById(int $id, array $data): bool
    {
        $model = static::find($id);
        if (!$model) {
            return false;
        }
        return $model->update($data);
    }

    /**
     * 批量更新（改）
     * 
     * @param array $ids
     * @param array $data
     * @return int
     */
    public static function batchUpdate(array $ids, array $data): int
    {
        return static::whereIn('id', $ids)->update($data);
    }

    /**
     * 根据条件更新（改）
     * 
     * @param array $conditions
     * @param array $data
     * @return int
     */
    public static function updateByConditions(array $conditions, array $data): int
    {
        $query = static::query();
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->update($data);
    }

    /**
     * 根据ID删除（删）
     * 
     * @param int $id
     * @return bool
     */
    public static function deleteById(int $id): bool
    {
        $model = static::find($id);
        if (!$model) {
            return false;
        }
        return $model->delete();
    }

    /**
     * 批量删除（删）
     * 
     * @param array $ids
     * @return int
     */
    public static function batchDelete(array $ids): int
    {
        return static::whereIn('id', $ids)->delete();
    }

    /**
     * 根据条件删除（删）
     * 
     * @param array $conditions
     * @return int
     */
    public static function deleteByConditions(array $conditions): int
    {
        $query = static::query();
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->delete();
    }

    /**
     * 根据ID恢复软删除
     * 
     * @param int $id
     * @return bool
     */
    public static function restoreById(int $id): bool
    {
        $model = static::withTrashed()->find($id);
        if (!$model) {
            return false;
        }
        return $model->restore();
    }

    /**
     * 租户范围查询
     * 
     * @param Builder $query
     * @param string|null $tenantId
     * @return Builder
     */
    public function scopeByTenant(Builder $query, ?string $tenantId = null): Builder
    {
        if (!$this->tenantable) {
            return $query;
        }

        $tenantId = $tenantId ?? (Auth::check() ? Auth::user()->{$this->tenantIdColumn} : null);
        
        if ($tenantId) {
            return $query->where($this->tenantIdColumn, $tenantId);
        }

        return $query;
    }

    /**
     * 活跃状态范围查询
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeActiveStatus(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * 非活跃状态范围查询
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    /**
     * 最近更新范围查询
     * 
     * @param Builder $query
     * @param int $days
     * @return Builder
     */
    public function scopeRecentlyUpdated(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * 最近创建范围查询
     * 
     * @param Builder $query
     * @param int $days
     * @return Builder
     */
    public function scopeRecentlyCreated(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * 检查是否属于指定租户
     * 
     * @param string $tenantId
     * @return bool
     */
    public function belongsToTenant(string $tenantId): bool
    {
        if (!$this->tenantable) {
            return true;
        }

        return $this->{$this->tenantIdColumn} === $tenantId;
    }

    /**
     * 获取模型的所有填充字段
     * 
     * @return array
     */
    public function getFillableFields(): array
    {
        return $this->fillable;
    }

    /**
     * 获取模型的标签名称
     * 
     * @return string
     */
    public static function getModelLabel(): string
    {
        return class_basename(static::class);
    }

    /**
     * 切换状态
     * 
     * @param string $field 状态字段
     * @param array $states 状态值数组 ['active', 'inactive']
     * @return bool
     */
    public function toggleStatus(string $field = 'status', array $states = ['active', 'inactive']): bool
    {
        $currentState = $this->{$field};
        $newState = $currentState === $states[0] ? $states[1] : $states[0];
        
        return $this->update([$field => $newState]);
    }
}
