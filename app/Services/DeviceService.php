<?php

namespace App\Services;

use App\Models\User;
use App\Models\Device;
use Illuminate\Support\Str;

/**
 * 设备服务
 * 
 * 处理用户设备相关的业务逻辑
 * 支持多设备登录管理
 */
class DeviceService extends BaseService
{
    protected string $modelClass = Device::class;

    /**
     * 注册或更新设备
     *
     * @param User $user
     * @param array $deviceInfo
     * @return Device
     */
    public function registerDevice(User $user, array $deviceInfo): Device
    {
        $deviceId = $deviceInfo['device_id'] ?? $this->generateDeviceId();
        
        $device = Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'tenant_id' => $user->tenant_id,
                'device_name' => $deviceInfo['device_name'] ?? null,
                'device_type' => $deviceInfo['device_type'] ?? Device::TYPE_WEB,
                'platform' => $deviceInfo['platform'] ?? null,
                'browser' => $deviceInfo['browser'] ?? null,
                'os' => $deviceInfo['os'] ?? null,
                'ip' => $deviceInfo['ip'] ?? request()->ip(),
                'user_agent' => $deviceInfo['user_agent'] ?? request()->userAgent(),
                'last_active_at' => now(),
                'status' => Device::STATUS_ONLINE,
            ]
        );

        return $device;
    }

    /**
     * 生成设备ID
     *
     * @return string
     */
    protected function generateDeviceId(): string
    {
        return Str::random(32);
    }

    /**
     * 获取设备详情
     *
     * @param int $deviceId
     * @return Device|null
     */
    public function getDeviceById(int $deviceId): ?Device
    {
        return Device::find($deviceId);
    }

    /**
     * 获取用户的所有设备
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserDevices(User $user)
    {
        return Device::where('user_id', $user->id)
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * 获取用户的在线设备
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOnlineDevices(User $user)
    {
        return Device::where('user_id', $user->id)
            ->online()
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * 设置设备为在线
     *
     * @param int $deviceId
     * @return bool
     */
    public function setOnline(int $deviceId): bool
    {
        return Device::where('id', $deviceId)->update([
            'status' => Device::STATUS_ONLINE,
            'last_active_at' => now(),
        ]) > 0;
    }

    /**
     * 设置设备为离线
     *
     * @param int $deviceId
     * @return bool
     */
    public function setOffline(int $deviceId): bool
    {
        return Device::where('id', $deviceId)->update([
            'status' => Device::STATUS_OFFLINE,
        ]) > 0;
    }

    /**
     * 删除设备
     *
     * @param int $deviceId
     * @return bool
     */
    public function deleteDevice(int $deviceId): bool
    {
        return Device::where('id', $deviceId)->delete() > 0;
    }

    /**
     * 删除用户的所有设备
     *
     * @param User $user
     * @return bool
     */
    public function deleteAllUserDevices(User $user): bool
    {
        return Device::where('user_id', $user->id)->delete() > 0;
    }

    /**
     * 更新设备活跃时间
     *
     * @param int $deviceId
     * @return bool
     */
    public function updateActiveTime(int $deviceId): bool
    {
        return Device::where('id', $deviceId)->update([
            'last_active_at' => now(),
        ]) > 0;
    }

    /**
     * 检查设备是否在线
     *
     * @param int $deviceId
     * @return bool
     */
    public function isOnline(int $deviceId): bool
    {
        $device = Device::find($deviceId);
        return $device && $device->status === Device::STATUS_ONLINE;
    }

    /**
     * 获取设备数量统计
     *
     * @param User $user
     * @return array
     */
    public function getDeviceStats(User $user): array
    {
        $total = Device::where('user_id', $user->id)->count();
        $online = Device::where('user_id', $user->id)->online()->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $total - $online,
        ];
    }

    /**
     * 清理长时间未活跃的设备
     *
     * @param int $days 未活跃天数
     * @return int 删除的设备数量
     */
    public function cleanInactiveDevices(int $days = 30): int
    {
        return Device::where('last_active_at', '<', now()->subDays($days))
            ->delete();
    }
}
