<?php

namespace tests\unit\mocks;

use Laxity7\Yii2\Components\Scheduler\Commands\SchedulerController;

class MockSchedulerController extends SchedulerController
{
    public string $output = '';

    public function stdout($string): int
    {
        $this->output .= $string;

        return strlen($string);
    }
}
