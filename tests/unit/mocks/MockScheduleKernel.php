<?php

namespace tests\unit\mocks;

use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use Laxity7\Yii2\Components\Scheduler\ScheduleKernelInterface;

class MockScheduleKernel implements ScheduleKernelInterface
{
    /** @var callable|null */
    public static $scheduleCallback;

    public function schedule(Schedule $schedule): void
    {
        if (is_callable(self::$scheduleCallback)) {
            call_user_func(self::$scheduleCallback, $schedule);
        }
    }
}
