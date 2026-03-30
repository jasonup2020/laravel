<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 权限模型
 */
class Permission extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
        'controller',
        'action',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 获取权限的角色
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role', 'permission_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * 作用域：启用的权限
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 作用域：根据模块查询
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
