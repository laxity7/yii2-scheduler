<?php

namespace Laxity7\Yii2\Components\Scheduler\Commands;

use Laxity7\Yii2\Components\Scheduler\Scheduler;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class SchedulerController extends Controller
{
    private Scheduler $scheduler;

    public function __construct($id, $module, $config = [])
    {
        $this->scheduler = Yii::$app->scheduler;
        $this->scheduler->setController($this);
        parent::__construct($id, $module, $config);
    }

    public function actionRun(): int
    {
        $this->scheduler->run();

        return ExitCode::OK;
    }

    public function actionListen(): int
    {
        $this->scheduler->listen();

        return ExitCode::OK;
    }
}
