<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 用户登录事件
 */
class UserLoginEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public array $deviceInfo;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param array $deviceInfo
     */
    public function __construct(User $user, array $deviceInfo = [])
    {
        $this->user = $user;
        $this->deviceInfo = $deviceInfo;
    }
}
