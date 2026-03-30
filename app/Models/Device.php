<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 设备模型
 */
class Device extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'devices';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_type',
        'platform',
        'browser',
        'ip_address',
        'user_agent',
        'access_token',
        'last_active_at',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status' => 'integer',
        'last_active_at' => 'datetime',
    ];

    const STATUS_OFFLINE = 0;
    const STATUS_ONLINE = 1;
    
    const TYPE_WEB = 'web';
    const TYPE_MOBILE = 'mobile';
    const TYPE_DESKTOP = 'desktop';

    /**
     * 获取设备的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 作用域：在线设备
     */
    public function scopeOnline($query)
    {
        return $query->where('status', self::STATUS_ONLINE);
    }

    /**
     * 作用域：根据用户查询
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
