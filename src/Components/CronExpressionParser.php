<?php

namespace Laxity7\Yii2\Components\Scheduler\Components;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class CronExpressionParser
{
    /**
     * Checks whether Cron-expression corresponds to the specified date.
     *
     * @param string            $expression
     * @param DateTimeInterface $date
     *
     * @return bool
     */
    public function isDue(string $expression, DateTimeInterface $date): bool
    {
        $parts = preg_split('/\s+/', $expression);
        if ($parts === false || count($parts) !== 5) {
            return false; // Wrong format or preg_split error
        }

        $minute = $parts[0];
        $hour = $parts[1];
        $dayOfMonth = $parts[2];
        $month = $parts[3];
        $dayOfWeek = $parts[4];

        $currentMinute = $date->format('i');
        $currentHour = $date->format('H');
        $currentDayOfMonth = $date->format('d');
        $currentMonth = $date->format('m');
        $currentDayOfWeek = $date->format('w'); // 0 (for Sunday) through 6 (for Saturday)

        if (!$this->match($minute, $currentMinute) ||
            !$this->match($hour, $currentHour) ||
            !$this->match($month, $currentMonth)) {
            return false;
        }

        // According to vixie-cron, if both day-of-month and day-of-week are restricted (not "*"),
        // the task runs if EITHER field matches the current time.
        if ($dayOfMonth !== '*' && $dayOfWeek !== '*') {
            return $this->match($dayOfMonth, $currentDayOfMonth) || $this->match($dayOfWeek, $currentDayOfWeek);
        }

        // If at least one of them is a wildcard, it's an AND condition.
        return $this->match($dayOfMonth, $currentDayOfMonth) && $this->match($dayOfWeek, $currentDayOfWeek);
    }

    /**
     * Calculates the next run date for the given expression.
     *
     * @param string            $expression
     * @param DateTimeImmutable $from
     *
     * @return DateTime
     * @throws \Exception
     */
    public function getNextRunDate(string $expression, DateTimeImmutable $from): DateTime
    {
        $currentDate = DateTime::createFromImmutable($from);

        // Start checking from the next minute to avoid returning the current minute if it's due
        $currentDate->modify('+1 minute');
        $currentDate->setTime((int)$currentDate->format('H'), (int)$currentDate->format('i'), 0);

        // Safeguard to prevent infinite loops, max 5 years
        for ($i = 0; $i < 2628000; $i++) {
            if ($this->isDue($expression, $currentDate)) {
                return $currentDate;
            }
            $currentDate->modify('+1 minute');
        }

        throw new InvalidArgumentException('Could not find next run date for expression: ' . $expression);
    }

    /**
     * Checks the correspondence of part of the expression to the current time value.
     *
     * @param string $part
     * @param string $value
     */
    private function match(string $part, string $value): bool
    {
        if ($part === '*') {
            return true;
        }

        if (strpos($part, ',') !== false) {
            $values = explode(',', $part);

            // Cast all values to int for comparison
            return in_array((int)$value, array_map('intval', $values), true);
        }

        if (strpos($part, '*/') === 0) {
            $step = (int)substr($part, 2);

            return $step > 0 && ((int)$value % $step === 0);
        }

        if (strpos($part, '-') !== false) {
            [$start, $end] = explode('-', $part);

            return (int)$value >= (int)$start && (int)$value <= (int)$end;
        }

        return (int)$part === (int)$value;
    }
}
