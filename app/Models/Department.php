<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 部门模型
 */
class Department extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'departments';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'code',
        'sort',
        'leader',
        'phone',
        'email',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 获取父部门
     */
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id', 'id');
    }

    /**
     * 获取子部门
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id', 'id')->orderBy('sort');
    }

    /**
     * 获取部门的用户
     */
    public function users()
    {
        return $this->hasMany(User::class, 'department_id', 'id');
    }

    /**
     * 递归获取所有子部门
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * 作用域：启用的部门
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
     * 作用域：根部门
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}
