<?php

namespace Laxity7\Yii2\Components\Scheduler\Components;

use Yii;
use yii\console\Controller;

/**
 * Class Event
 * Представляет одну запланированную задачу. Является объектом-значением (Value Object).
 */
class Event
{
    public string $expression = '* * * * *';
    public ?string $command = null;
    public $callback = null;

    /**
     * @var array<mixed> Parameters to pass to the command or callback.
     */
    public array $parameters = [];
    /**
     * @var bool If true, a mutex will be used to prevent overlapping.
     */
    public bool $withoutOverlapping = false;

    /**
     * @param string|callable $task
     */
    public function __construct($task)
    {
        if (is_string($task)) {
            $this->command = $task;
        } elseif (is_callable($task)) {
            $this->callback = $task;
        }
    }

    public function run(Controller $consoleController): void
    {
        if ($this->command) {
            // For commands, parameters are passed as the second argument to runAction
            $consoleController->runAction(str_replace('/', '-', $this->command), $this->parameters);
        } elseif ($this->callback) {
            // For callbacks, the container will resolve dependencies and inject parameters
            Yii::$container->invoke($this->callback, $this->parameters);
        }
    }

    /**
     * Sets the parameters for the task.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function withParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Sets a flag requiring a mutex to be used for this task.
     * @return $this
     */
    public function withoutOverlapping(): self
    {
        $this->withoutOverlapping = true;

        return $this;
    }

    public function isDue(CronExpressionParser $parser, \DateTimeInterface $date): bool
    {
        return $parser->isDue($this->expression, $date);
    }

    public function getSummaryForDisplay(): string
    {
        if ($this->command) {
            return 'yii ' . $this->command;
        }
        if (is_string($this->callback)) {
            return $this->callback;
        }
        if (is_array($this->callback)) {
            $class = is_object($this->callback[0]) ? get_class($this->callback[0]) : $this->callback[0];
            $method = $this->callback[1];

            return "{$class}::{$method}";
        }

        return 'Callable';
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time);

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }
}
