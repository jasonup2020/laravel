<?php

namespace App\Listeners;

use App\Events\UserLoginEvent;
use App\Events\UserLogoutEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * 记录用户活动监听器
 */
class LogUserActivity implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle user login event.
     *
     * @param UserLoginEvent $event
     * @return void
     */
    public function handleLogin(UserLoginEvent $event): void
    {
        $user = $event->user;
        $deviceInfo = $event->deviceInfo;

        \Log::info('User logged in', [
            'user_id' => $user->id,
            'username' => $user->username,
            'tenant_id' => $user->tenant_id,
            'ip' => $deviceInfo['ip'] ?? null,
            'device' => $deviceInfo['device_name'] ?? null,
            'time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Handle user logout event.
     *
     * @param UserLogoutEvent $event
     * @return void
     */
    public function handleLogout(UserLogoutEvent $event): void
    {
        $user = $event->user;

        \Log::info('User logged out', [
            'user_id' => $user->id,
            'username' => $user->username,
            'tenant_id' => $user->tenant_id,
            'time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param \Illuminate\Events\Dispatcher $events
     * @return void
     */
    public function subscribe($events): void
    {
        $events->listen(
            UserLoginEvent::class,
            [self::class, 'handleLogin']
        );

        $events->listen(
            UserLogoutEvent::class,
            [self::class, 'handleLogout']
        );
    }
}
