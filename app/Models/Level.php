<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 职级模型
 */
class Level extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'levels';

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
     * 获取职级的用户
     */
    public function users()
    {
        return $this->hasMany(User::class, 'level_id', 'id');
    }

    /**
     * 作用域：启用的职级
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
