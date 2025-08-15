<?php

namespace tests\unit\mocks;

use Laxity7\Yii2\Components\Scheduler\Components\Schedule;
use Laxity7\Yii2\Components\Scheduler\KernelScheduleInterface;

class TestKernel implements KernelScheduleInterface
{
    /** @var callable */
    public static $scheduleCallback;

    public function schedule(Schedule $schedule): void
    {
        if (is_callable(self::$scheduleCallback)) {
            call_user_func(self::$scheduleCallback, $schedule);
        }
    }
}
