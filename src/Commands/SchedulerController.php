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
        $globalTimeZoneName = $this->scheduler->resolveTimeZone(null);
        $now = new DateTimeImmutable('now', new DateTimeZone($globalTimeZoneName));

        $rows = [];
        foreach ($schedule->getTasks() as $task) {
            $taskTimeZoneName = $this->scheduler->resolveTimeZone($task->timeZone);
            try {
                $nextRunDate = $parser->getNextRunDate($task->expression, $now, $taskTimeZoneName);
                // Convert back to global timezone for comparison/display
                $nextRunDate->setTimezone($now->getTimezone());
                $nextRun = $nextRunDate->format('Y-m-d H:i:s');
                $diff = $this->formatDiff($now, $nextRunDate);
                $nextRun .= $this->ansiFormat(" ({$diff})", Console::FG_GREY);
            } catch (Exception $e) {
                $nextRun = $this->ansiFormat('Invalid Expression', Console::FG_RED);
            }
            $rows[] = [
                'expression'  => $this->ansiFormat($task->expression, Console::FG_CYAN),
                'timeZone'    => $taskTimeZoneName,
                'nextRun'     => $nextRun,
                'description' => $task->getName(),
            ];
        }

        if ($rows === []) {
            $this->stdout("No tasks have been scheduled.\n");

            return ExitCode::OK;
        }

        $col1Width = 24; // Expression
        $col2Width = 18; // TimeZone
        $col3Width = 46; // Next Run Time (Date + Diff)

        $output .= str_pad("Expression", $col1Width) .
            str_pad("TimeZone", $col2Width) .
            str_pad("Next Run Time", $col3Width) .
            "Description\n";
        $output .= str_repeat('-', $col1Width - 1) . ' ' .
            str_repeat('-', $col2Width - 1) . ' ' .
            str_repeat('-', $col3Width - 1) . ' ' .
            str_repeat('-', 40) . "\n";

        foreach ($rows as $row) {
            $expression = (string)($row['expression'] ?? '');
            $timeZone = (string)($row['timeZone'] ?? '');
            $nextRun = (string)($row['nextRun'] ?? '');
            $description = (string)($row['description'] ?? '');

            // Calculate padding based on visible length (stripping ANSI codes)
            $plainExpression = preg_replace('/\033\[[\d;]*m/', '', $expression);
            $plainTimeZone = preg_replace('/\033\[[\d;]*m/', '', $timeZone);
            $plainNextRun = preg_replace('/\033\[[\d;]*m/', '', $nextRun);

            $visibleLen1 = $plainExpression === null ? 0 : mb_strlen($plainExpression);
            $visibleLen2 = $plainTimeZone === null ? 0 : mb_strlen($plainTimeZone);
            $visibleLen3 = $plainNextRun === null ? 0 : mb_strlen($plainNextRun);

            $padding1 = $col1Width - $visibleLen1;
            $padding2 = $col2Width - $visibleLen2;
            $padding3 = $col3Width - $visibleLen3;

            $output .= $expression . str_repeat(' ', max(0, $padding1)) .
                $timeZone . str_repeat(' ', max(0, $padding2)) .
                $nextRun . str_repeat(' ', max(0, $padding3)) .
                $description . "\n";
        }

        $this->stdout($output);

        return ExitCode::OK;
    }

    /**
     * Formats the difference between two dates as a human-readable string.
     *
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     *
     * @return string
     */
    private function formatDiff(\DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $diff = $from->diff($to);

        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y . 'y';
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . 'mo';
        }
        if ($diff->d > 0) {
            $parts[] = $diff->d . 'd';
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . 'h';
        }
        if ($diff->i > 0) {
            $parts[] = $diff->i . 'm';
        }
        if ($diff->s > 0 && count($parts) < 2) {
            $parts[] = $diff->s . 's';
        }

        if ([] === $parts) {
            return 'now';
        }

        return 'in ' . implode(' ', array_slice($parts, 0, 2));
    }
}
