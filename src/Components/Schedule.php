<?php

namespace Laxity7\Yii2\Components\Scheduler\Components;

/**
 * Contains a list of scheduled events and provides methods to add commands or callbacks.
 */
class Schedule
{
    /**
     * @var Event[] List of scheduled events.
     */
    private array $events = [];

    /**
     * Adds a console command to the schedule.
     *
     * @param string $command e.g., 'cache/flush-all'
     *
     * @return Event
     */
    public function command(string $command): Event
    {
        $event = new Event($command);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Adds an anonymous function to the schedule.
     *
     * @param callable $callback
     *
     * @return Event
     */
    public function call(callable $callback): Event
    {
        $event = new Event($callback);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Returns all tasks that are due to run.
     *
     * @param CronExpressionParser $parser
     * @param \DateTimeInterface   $date
     *
     * @return Event[]
     */
    public function dueEvents(CronExpressionParser $parser, \DateTimeInterface $date): array
    {
        return array_filter($this->events, function (Event $event) use ($parser, $date) {
            return $event->isDue($parser, $date);
        });
    }
}
