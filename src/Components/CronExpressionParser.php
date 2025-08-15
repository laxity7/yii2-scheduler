<?php

namespace Laxity7\Yii2\Components\Scheduler\Components;

class CronExpressionParser
{
    /**
     * Checks whether Cron-expression corresponds to the specified date.
     *
     * @param string             $expression
     * @param \DateTimeInterface $date
     *
     * @return bool
     */
    public function isDue(string $expression, \DateTimeInterface $date): bool
    {
        $parts = preg_split('/\s+/', $expression);
        if (count($parts) !== 5) {
            return false; // Wrong format
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

        if ($dayOfMonth === '*' && $dayOfWeek === '*') {
            return true;
        }

        if ($dayOfMonth !== '*' && $dayOfWeek !== '*') {
            return $this->match($dayOfMonth, $currentDayOfMonth) || $this->match($dayOfWeek, $currentDayOfWeek);
        }

        return $this->match($dayOfMonth, $currentDayOfMonth) && $this->match($dayOfWeek, $currentDayOfWeek);
    }

    /**
     * Checks the correspondence of part of the expression to the current time value.
     *
     * @param string $part
     * @param string $value
     *
     * @return bool
     */
    private function match(string $part, string $value): bool
    {
        if ($part === '*') {
            return true;
        }

        if (strpos($part, ',') !== false) {
            return in_array($value, explode(',', $part));
        }

        if (strpos($part, '*/') === 0) {
            $step = (int)substr($part, 2);

            return $step > 0 && ($value % $step === 0);
        }

        if (strpos($part, '-') !== false) {
            [$start, $end] = explode('-', $part);

            return $value >= $start && $value <= $end;
        }

        return $part === (string)(int)$value;
    }
}
