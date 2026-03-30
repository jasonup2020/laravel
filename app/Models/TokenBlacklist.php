<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token黑名单模型
 */
class TokenBlacklist extends Model
{
    protected $table = 'token_blacklists';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'token',
        'token_type',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tenant_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    const TYPE_ACCESS = 'access';
    const TYPE_REFRESH = 'refresh';

    /**
     * 获取用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取租户
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * 检查Token是否在黑名单中
     */
    public static function isBlacklisted(string $token): bool
    {
        return static::where('token', $token)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * 添加Token到黑名单
     */
    public static function addToBlacklist(
        int $userId,
        int $tenantId,
        string $token,
        string $tokenType,
        \DateTime $expireAt,
        string $reason = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'token' => $token,
            'token_type' => $tokenType,
            'reason' => $reason,
            'expires_at' => $expireAt,
        ]);
    }

    /**
     * 清理过期的黑名单记录
     */
    public static function cleanExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
