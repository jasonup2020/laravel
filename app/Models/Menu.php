<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 菜单模型
 */
class Menu extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'menus';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'icon',
        'path',
        'component',
        'redirect',
        'sort',
        'is_hidden',
        'is_external',
        'is_cached',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'is_hidden' => 'boolean',
        'is_external' => 'boolean',
        'is_cached' => 'boolean',
        'sort' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 获取父菜单
     */
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id', 'id');
    }

    /**
     * 获取子菜单
     */
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id', 'id')->orderBy('sort');
    }

    /**
     * 递归获取所有子菜单
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * 作用域：启用的菜单
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
     * 作用域：根菜单
     */
    public function scopeRoots($query)
    {
        return $query->where('parent_id', 0);
    }

    /**
     * 作用域：非隐藏菜单
     */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', 0);
    }
}
