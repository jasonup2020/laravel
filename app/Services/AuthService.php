<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 认证服务
 * 
 * 处理用户登录、登出、Token刷新等认证相关业务逻辑
 * 支持JWT双令牌机制（Access Token + Refresh Token）
 */
class AuthService extends BaseService {

    protected TokenService $tokenService;
    protected DeviceService $deviceService;

    public function __construct(TokenService $tokenService, DeviceService $deviceService) {
        $this->tokenService = $tokenService;
        $this->deviceService = $deviceService;
    }

    /**
     * 用户登录
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param array $deviceInfo 设备信息
     * @return array
     * @throws BusinessException
     */
    public function login(string $email, string $password, array $deviceInfo = []): array {
        // 查找用户
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        // 验证用户状态
        if ($user->status !== User::STATUS_ENABLED) {
            throw new BusinessException('用户已被禁用');
        }

        // 验证租户状态
        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant || !$tenant->isEnabled()) {
            throw new BusinessException('租户不可用');
        }

        // 验证密码
        if (!$user->verifyPassword($password)) {
            throw new BusinessException('密码错误');
        }

        DB::beginTransaction();
        try {
            // 注册或更新设备
            $device = $this->deviceService->registerDevice($user, $deviceInfo);

            // 生成双令牌
            $tokens = $this->tokenService->generateTokens($user, $device);

            // 更新用户登录信息
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $deviceInfo['ip'] ?? request()->ip(),
                'login_count' => $user->login_count + 1,
            ]);

            DB::commit();

            return [
//                'user' => ["email"=>$user->email,"last_login_at"=>$user->last_login_at,"last_login_ip"=>$user->last_login_ip],
                'user' => ["email"=>$user->email,"locale"=>$user->locale],
                'device' => $device->device_type,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $tokens['expires_in'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException('登录失败: ' . $e->getMessage());
        }
    }

    /**
     * 用户登出
     *
     * @param User $user
     * @param string $token
     * @return bool
     */
    public function logout(User $user, string $token): bool {
        // 将Token加入黑名单
        $this->tokenService->blacklistToken($user, $token, TokenService::TYPE_ACCESS, '用户登出');

        // 更新设备状态为离线
        $deviceId = $this->tokenService->getDeviceIdFromToken($token);
        if ($deviceId) {
            $this->deviceService->setOffline((int) $deviceId);
        }

        return true;
    }

    /**
     * 刷新Token
     *
     * @param string $refreshToken
     * @return array
     * @throws BusinessException
     */
    public function refreshToken(string $refreshToken): array {
        // 验证Refresh Token
        $payload = $this->tokenService->validateToken($refreshToken, TokenService::TYPE_REFRESH);

        if (!$payload) {
            throw new BusinessException('无效的Refresh Token');
        }

        // 检查是否在黑名单中
        if ($this->tokenService->isBlacklisted($refreshToken)) {
            throw new BusinessException('Token已失效');
        }

        $user = User::find($payload['user_id']);
        if (!$user || $user->status !== User::STATUS_ENABLED) {
            throw new BusinessException('用户不可用');
        }

        // 将旧的Refresh Token加入黑名单
        $this->tokenService->blacklistToken($user, $refreshToken, TokenService::TYPE_REFRESH, 'Token刷新');

        // 获取或创建设备
        $device = null;
        if (isset($payload['device_id'])) {
            $device = $this->deviceService->getDeviceById($payload['device_id']);
        }

        // 如果设备不存在，创建新设备
        if (!$device) {
            $device = $this->deviceService->registerDevice($user, [
                'device_id' => Str::random(32),
                'device_type' => 'web',
            ]);
        }

        $tokens = $this->tokenService->generateTokens($user, $device);

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
        ];
    }

    /**
     * 获取当前用户信息
     *
     * @param User $user
     * @return array
     */
    public function getCurrentUser(User $user): array {
        $user->load(['roles.permissions', 'department', 'position', 'level']);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'gender' => $user->gender,
            'birthday' => $user->birthday,
            'signature' => $user->signature,
            'department' => $user->department,
            'position' => $user->position,
            'level' => $user->level,
            'roles' => $user->roles,
            'permissions' => $user->getPermissions()->pluck('code')->unique()->values(),
        ];
    }

    /**
     * 修改密码
     *
     * @param User $user
     * @param string $oldPassword
     * @param string $newPassword
     * @return bool
     * @throws BusinessException
     */
    public function changePassword(User $user, string $oldPassword, string $newPassword): bool {
        // 验证旧密码
        if (!$user->verifyPassword($oldPassword)) {
            throw new BusinessException('原密码错误');
        }

        // 更新密码
        $user->password = $newPassword;
        $user->save();

        // 将所有Token加入黑名单
        $this->tokenService->blacklistAllUserTokens($user, '密码修改');

        return true;
    }

    /**
     * 用户注册
     *
     * @param array $data
     * @return array
     * @throws BusinessException
     */
    public function register(array $data): array {

        // 验证邮箱是否已存在
        if (User::where('email', $data['email'])->exists()) {
            throw new BusinessException('邮箱已被注册');
        }

            // 创建用户
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'tenant_id' => 1, // 默认租户
                'status' => User::STATUS_ENABLED,
            ]);

            // 自动登录 - 注册设备
            $device = $this->deviceService->registerDevice($user, [
                'device_id' => Str::random(32),
                'device_type' => 'web',
            ]);

            // 生成Token
            $tokens = $this->tokenService->generateTokens($user, $device);

        return [
            'user' => $user->email,
            'device' => $device->device_type,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
        ];
    }

    /**
     * 重置密码
     *
     * @param int $userId
     * @param string $newPassword
     * @return bool
     * @throws BusinessException
     */
    public function resetPassword(int $userId, string $newPassword): bool {
        $user = User::findOrFail($userId);

        $user->password = $newPassword;
        $user->save();

        // 将所有Token加入黑名单
        $this->tokenService->blacklistAllUserTokens($user, '密码重置');

        return true;
    }
}
