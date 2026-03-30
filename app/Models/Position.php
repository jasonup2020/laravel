<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 岗位模型
 */
class Position extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'positions';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'sort',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 获取岗位的用户
     */
    public function users()
    {
        return $this->hasMany(User::class, 'position_id', 'id');
    }

    /**
     * 作用域：启用的岗位
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
}
