<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * 基础模型类
 * 
 * 所有模型都应继承此类，提供通用功能：
 * - 自动维护创建者和更新者
 * - 软删除支持
 * - 租户隔离
 * - 通用查询作用域
 * 
 * @package App\Models
 * @author  SaaS Platform
 * @version 1.0.0
 */
abstract class BaseModel extends Model
{
    use SoftDeletes;

    /**
     * 自动维护创建者和更新者
     *
     * @var bool
     */
    protected static $autoMaintainUser = true;

    /**
     * 启用租户隔离
     *
     * @var bool
     */
    protected static $enableTenantIsolation = true;

    /**
     * 租户ID字段名
     *
     * @var string
     */
    protected $tenantIdColumn = 'tenant_id';

    /**
     * 模型启动方法
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        // 自动维护创建者和更新者
        if (static::$autoMaintainUser) {
            static::creating(function (self $model) {
                if (Auth::check()) {
                    $userId = Auth::id();
                    
                    if (!$model->isDirty('created_by') && in_array('created_by', $model->getFillable())) {
                        $model->created_by = $userId;
                    }
                    
                    if (!$model->isDirty('updated_by') && in_array('updated_by', $model->getFillable())) {
                        $model->updated_by = $userId;
                    }
                }
            });

            static::updating(function (self $model) {
                if (Auth::check() && !$model->isDirty('updated_by') && in_array('updated_by', $model->getFillable())) {
                    $model->updated_by = Auth::id();
                }
            });
        }

        // 租户隔离全局作用域
        if (static::$enableTenantIsolation) {
            static::addGlobalScope('tenant', function ($query) {
                $tenantId = tenant_id();
                
                if ($tenantId && in_array('tenant_id', $query->getModel()->getFillable())) {
                    $query->where('tenant_id', $tenantId);
                }
            });
        }
    }

    /**
     * 获取创建者
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 获取更新者
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 获取租户
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * 禁用租户隔离
     *
     * @return static
     */
    public static function withoutTenant(): static
    {
        return static::withoutGlobalScope('tenant');
    }

    /**
     * 查询指定租户的数据
     *
     * @param int $tenantId 租户ID
     * @return static
     */
    public static function forTenant(int $tenantId): static
    {
        return static::withoutTenant()->where('tenant_id', $tenantId);
    }

    /**
     * 查询活跃记录
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 查询非活跃记录
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    /**
     * 查询指定状态
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $status 状态值
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 按创建时间排序（最新在前）
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 按创建时间排序（最早在前）
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * 按更新时间排序（最新在前）
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecentlyUpdated($query)
    {
        return $query->orderBy('updated_at', 'desc');
    }

    /**
     * 批量更新
     *
     * @param array $ids ID数组
     * @param array $data 更新数据
     * @return int
     */
    public static function batchUpdate(array $ids, array $data): int
    {
        return static::whereIn('id', $ids)->update($data);
    }

    /**
     * 批量删除
     *
     * @param array $ids ID数组
     * @return int
     */
    public static function batchDelete(array $ids): int
    {
        return static::whereIn('id', $ids)->delete();
    }

    /**
     * 批量恢复
     *
     * @param array $ids ID数组
     * @return int
     */
    public static function batchRestore(array $ids): int
    {
        return static::onlyTrashed()->whereIn('id', $ids)->restore();
    }

    /**
     * 获取字段值
     *
     * @param string $field 字段名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getFieldValue(string $field, $default = null)
    {
        return $this->getAttribute($field) ?? $default;
    }

    /**
     * 设置字段值
     *
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @return static
     */
    public function setFieldValue(string $field, $value): static
    {
        $this->setAttribute($field, $value);
        return $this;
    }

    /**
     * 判断字段是否被修改
     *
     * @param string $field 字段名
     * @return bool
     */
    public function isFieldDirty(string $field): bool
    {
        return $this->isDirty($field);
    }

    /**
     * 获取修改的字段
     *
     * @return array
     */
    public function getDirtyFields(): array
    {
        return $this->getDirty();
    }

    /**
     * 复制模型
     *
     * @param array $except 排除的字段
     * @return static
     */
    public function replicateModel(array $except = []): static
    {
        $defaults = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $except = array_merge($defaults, $except);
        
        return $this->replicate($except);
    }

    /**
     * 转换为数组（隐藏敏感字段）
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // 隐藏敏感字段
        $hidden = ['password', 'password_salt', 'remember_token'];
        
        foreach ($hidden as $field) {
            unset($array[$field]);
        }
        
        return $array;
    }
}
