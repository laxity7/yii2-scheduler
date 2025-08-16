<?php

namespace Laxity7\Yii2\Components\Scheduler\Commands;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use Laxity7\Yii2\Components\Scheduler\Scheduler;
use Yii;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class SchedulerController extends Controller
{
    private Scheduler $scheduler;

    /**
     * @throws InvalidConfigException
     */
    public function __construct($id, $module, $config = [])
    {
        $schedulerComponent = Yii::$app->get('scheduler');
        if (!$schedulerComponent instanceof Scheduler) {
            throw new InvalidConfigException('The "scheduler" component must be an instance of ' . Scheduler::class);
        }
        $this->scheduler = $schedulerComponent;
        $this->scheduler->setController($this);
        parent::__construct($id, $module, $config);
    }

    /**
     * Runs a one-time execution of scheduled tasks.
     */
    public function actionRun(): int
    {
        if (!$this->scheduler->getMutex()->acquire('scheduler-run', 5)) {
            $this->stdout("Scheduler is already running. Exiting.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

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
        $decodedIdentifier = base64_decode($taskIdentifier, true);
        if ($decodedIdentifier === false) {
            Yii::error("Failed to decode task identifier: {$taskIdentifier}", 'scheduler');

            return ExitCode::DATAERR;
        }
        $this->scheduler->runSingleTaskByIdentifier($decodedIdentifier);

        return ExitCode::OK;
    }

    /**
     * Displays a list of all scheduled tasks.
     */
    public function actionList(): int
    {
        $output = $this->ansiFormat("Scheduled Tasks List\n", Console::BOLD);

        $schedule = $this->scheduler->getSchedule();
        $parser = new CronExpressionParser();
        $now = new DateTimeImmutable('now', new DateTimeZone(Yii::$app->timeZone));

        $rows = [];
        foreach ($schedule->getTasks() as $task) {
            try {
                $nextRunDate = $parser->getNextRunDate($task->expression, $now);
                $nextRun = $nextRunDate->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $nextRun = $this->ansiFormat('Invalid Expression', Console::FG_RED);
            }
            $rows[] = [
                'expression'  => $this->ansiFormat($task->expression, Console::FG_CYAN),
                'nextRun'     => $nextRun,
                'description' => $task->getName(),
            ];
        }

        if ([] === $rows) {
            $this->stdout("No tasks have been scheduled.\n");

            return ExitCode::OK;
        }

        $col1Width = 24;
        $col2Width = 22;

        $output .= str_pad("Expression", $col1Width) .
            str_pad("Next Run Time", $col2Width) .
            "Description\n";
        $output .= str_repeat('-', $col1Width - 1) . ' ' .
            str_repeat('-', $col2Width - 1) . ' ' .
            str_repeat('-', 40) . "\n";

        foreach ($rows as $row) {
            $expression = (string)($row['expression'] ?? '');
            $nextRun = (string)($row['nextRun'] ?? '');
            $description = (string)($row['description'] ?? '');

            $plainExpression = preg_replace('/\033\[[\d;]*m/', '', $expression);
            $visibleLen1 = $plainExpression === null ? 0 : mb_strlen($plainExpression);
            $padding1 = $col1Width - $visibleLen1;

            $output .= $expression .
                str_repeat(' ', max(0, $padding1)) .
                str_pad($nextRun, $col2Width) .
                $description . "\n";
        }

        $this->stdout($output);

        return ExitCode::OK;
    }
}
