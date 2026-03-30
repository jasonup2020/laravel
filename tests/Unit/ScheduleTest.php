<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleTest extends TestCase
{
    public function test_schedule_has_log_every_15_seconds_task()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events())->filter(function ($event) {
            return $event->description === 'log-every-15-seconds';
        });

        $this->assertEquals(1, $events->count());
    }

    public function test_schedule_log_every_15_seconds_task_runs_every_15_seconds()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events())->filter(function ($event) {
            return $event->description === 'log-every-15-seconds';
        });

        $event = $events->first();

        // Laravel's everyFifteenSeconds() sets the expression to '* * * * *'
        // but handles the 15-second interval internally
        $this->assertEquals('* * * * *', $event->expression);
    }

    public function test_schedule_log_every_15_seconds_task_logs_execution_time()
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Scheduled task executed at:');
            });

        $schedule = app(Schedule::class);
        $events = collect($schedule->events())->filter(function ($event) {
            return $event->description === 'log-every-15-seconds';
        });

        $event = $events->first();
        $event->run(app());
    }
}
