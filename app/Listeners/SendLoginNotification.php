<?php

namespace App\Listeners;

use App\Events\UserLoginEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * 发送登录通知监听器
 */
class SendLoginNotification implements ShouldQueue
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
     * Handle the event.
     *
     * @param UserLoginEvent $event
     * @return void
     */
    public function handle(UserLoginEvent $event): void
    {
        $user = $event->user;
        $deviceInfo = $event->deviceInfo;

        // 发送登录通知（邮件、短信等）
        \Log::info('User login notification', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $deviceInfo['ip'] ?? null,
            'device' => $deviceInfo['device_name'] ?? null,
        ]);

        // TODO: 实现具体的通知逻辑
        // 例如：发送邮件通知
        // Mail::to($user->email)->send(new LoginNotificationMail($user, $deviceInfo));
    }
}
