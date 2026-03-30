<?php

namespace App\Services;

use App\Models\User;
use App\Models\Device;
use App\Models\TokenBlacklist;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

/**
 * Token服务
 * 
 * 处理JWT Token的生成、验证、刷新等操作
 * 实现双令牌机制（Access Token + Refresh Token）
 */
class TokenService
{
    const TYPE_ACCESS = 'access';
    const TYPE_REFRESH = 'refresh';

    protected string $secret;
    protected string $algorithm = 'HS256';
    protected int $accessTtl;
    protected int $refreshTtl;

    public function __construct()
    {
        $this->secret = config('saas.jwt.secret', env('JWT_SECRET', 'your-secret-key'));
        $this->accessTtl = config('saas.jwt.access_ttl', 7200); // 2小时
        $this->refreshTtl = config('saas.jwt.refresh_ttl', 604800); // 7天
    }

    /**
     * 生成双令牌
     *
     * @param User $user
     * @param Device $device
     * @return array
     */
    public function generateTokens(User $user, Device $device): array
    {
        $now = time();

        // 生成Access Token
        $accessPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'device_id' => $device->id,
            'type' => self::TYPE_ACCESS,
            'jti' => Str::random(32),
        ];

        // 生成Refresh Token
        $refreshPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'device_id' => $device->id,
            'type' => self::TYPE_REFRESH,
            'jti' => Str::random(32),
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $this->secret, $this->algorithm),
            'refresh_token' => JWT::encode($refreshPayload, $this->secret, $this->algorithm),
            'expires_in' => $this->accessTtl,
        ];
    }

    /**
     * 验证Token
     *
     * @param string $token
     * @param string|null $type
     * @return array|null
     */
    public function validateToken(string $token, ?string $type = null): ?array
    {
        try {
            \Log::info('JWT: Validating token', ['secret' => substr($this->secret, 0, 10), 'algorithm' => $this->algorithm]);
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            $payload = (array) $decoded;
            
            \Log::info('JWT: Token decoded successfully', ['payload' => $payload]);

            // 验证Token类型
            if ($type && $payload['type'] !== $type) {
                \Log::error('JWT: Token type mismatch', ['expected' => $type, 'actual' => $payload['type']]);
                return null;
            }

            // 检查黑名单
            if ($this->isBlacklisted($token)) {
                \Log::error('JWT: Token is blacklisted');
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            \Log::error('JWT: Token validation exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * 从Token中获取用户ID
     *
     * @param string $token
     * @return int|null
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        return $payload['user_id'] ?? null;
    }

    /**
     * 从Token中获取租户ID
     *
     * @param string $token
     * @return int|null
     */
    public function getTenantIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        return $payload['tenant_id'] ?? null;
    }

    /**
     * 从Token中获取设备ID
     *
     * @param string $token
     * @return int|null
     */
    public function getDeviceIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        return $payload['device_id'] ?? null;
    }

    /**
     * 检查Token是否在黑名单中
     *
     * @param string $token
     * @return bool
     */
    public function isBlacklisted(string $token): bool
    {
        return TokenBlacklist::isBlacklisted($token);
    }

    /**
     * 将Token加入黑名单
     *
     * @param User $user
     * @param string $token
     * @param string $type
     * @param string|null $reason
     * @return TokenBlacklist
     */
    public function blacklistToken(User $user, string $token, string $type, ?string $reason = null): TokenBlacklist
    {
        $payload = $this->validateToken($token, $type);
        
        if (!$payload) {
            // 如果Token无效，使用默认过期时间
            $expireAt = now()->addSeconds($type === self::TYPE_ACCESS ? $this->accessTtl : $this->refreshTtl);
        } else {
            $expireAt = \Carbon\Carbon::createFromTimestamp($payload['exp']);
        }

        return TokenBlacklist::addToBlacklist(
            $user->id,
            $user->tenant_id,
            $token,
            $type,
            $expireAt,
            $reason
        );
    }

    /**
     * 将用户所有Token加入黑名单
     *
     * @param User $user
     * @param string|null $reason
     * @return void
     */
    public function blacklistAllUserTokens(User $user, ?string $reason = null): void
    {
        // 这里可以通过Redis或其他缓存机制实现
        // 简化实现：记录一个用户级别的黑名单时间戳
        // 所有在此时间之前签发的Token都视为无效
        cache()->put("user_token_blacklist:{$user->id}", now()->timestamp, now()->addDays(30));
    }

    /**
     * 检查用户Token是否被全部拉黑
     *
     * @param User $user
     * @param string $token
     * @return bool
     */
    public function isUserTokensBlacklisted(User $user, string $token): bool
    {
        $blacklistTime = cache()->get("user_token_blacklist:{$user->id}");
        
        if (!$blacklistTime) {
            return false;
        }

        $payload = $this->validateToken($token);
        
        if (!$payload) {
            return true;
        }

        return $payload['iat'] < $blacklistTime;
    }

    /**
     * 清理过期的黑名单记录
     *
     * @return int
     */
    public function cleanExpiredBlacklist(): int
    {
        return TokenBlacklist::cleanExpired();
    }
}
