<?php

namespace Laxity7\Yii2\Components\Scheduler\Components;

/**
 * Contains a list of scheduled tasks and provides methods to add commands or callbacks.
 */
class Schedule
{
    /**
     * @var Task[] List of scheduled tasks.
     */
    private array $tasks = [];

    /**
     * Adds a console command to the schedule.
     *
     * @param string $command e.g., 'cache/flush-all'
     *
     * @return Task
     */
    public function command(string $command): Task
    {
        $task = new Task($command);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * Adds an anonymous function to the schedule.
     *
     * @param callable $callback
     *
     * @return Task
     */
    public function call(callable $callback): Task
    {
        $task = new Task($callback);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * Returns all tasks that are due to run.
     *
     * @param CronExpressionParser $parser
     * @param \DateTimeInterface   $date
     * @param non-empty-string     $globalTimeZoneName
     *
     * @return Task[]
     */
    public function dueTask(CronExpressionParser $parser, \DateTimeInterface $date, string $globalTimeZoneName): array
    {
        return array_filter($this->tasks, function (Task $task) use ($parser, $date, $globalTimeZoneName) {
            return $task->isDue($parser, $date, $globalTimeZoneName);
        });
    }

    /**
     * Returns all registered tasks.
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}
