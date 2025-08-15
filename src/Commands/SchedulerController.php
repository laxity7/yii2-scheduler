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

    /**
     * Runs a one-time execution of scheduled tasks.
     */
    public function actionRun(): int
    {
        $this->scheduler->run();

        return ExitCode::OK;
    }

    /**
     * Runs the scheduler in daemon mode (infinite loop).
     */
    public function actionListen(): int
    {
        $this->scheduler->listen();

        return ExitCode::OK;
    }

    /**
     * Executes a single task identified by its summary string.
     * This is intended to be called as a background process by the main scheduler.
     *
     * @param string $taskIdentifier The base64 encoded summary of the task to run.
     */
    public function actionExecute(string $taskIdentifier): int
    {
        // The main scheduler component is not available in the new process,
        // so we create a temporary instance to execute the task.
        $schedulerConfig = Yii::$app->components['scheduler'] ?? ['class' => Scheduler::class];
        if (!isset($schedulerConfig['class'])) {
            $schedulerConfig['class'] = Scheduler::class;
        }
        /** @var Scheduler $scheduler */
        $scheduler = Yii::createObject($schedulerConfig);

        $scheduler->runSingleTaskByIdentifier(base64_decode($taskIdentifier));

        return ExitCode::OK;
    }
}
