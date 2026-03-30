<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 角色模型
 */
class Role extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 获取角色的用户
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * 获取角色的权限
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    // tenant() 方法已在 BaseModel 中定义

    /**
     * 分配权限
     */
    public function assignPermissions(array $permissionIds)
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * 检查是否有某个权限
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('slug', $permission);
    }

    /**
     * 作用域：启用的角色
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 作用域：根据租户查询
     */
    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * 作用域：非系统角色
     */
    public function scopeNotSystem($query)
    {
        return $query;
    }
}
