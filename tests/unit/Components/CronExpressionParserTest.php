<?php

namespace tests\unit\Components;

use DateTimeImmutable;
use DateTimeZone;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser
 */
class CronExpressionParserTest extends TestCase
{
    private CronExpressionParser $parser;
    private DateTimeZone $timezone;

    protected function setUp(): void
    {
        $this->parser = new CronExpressionParser();
        $this->timezone = new DateTimeZone('UTC');
    }

    /**
     * @covers \Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser::isDue
     */
    public function testEveryMinute(): void
    {
        self::assertTrue($this->parser->isDue('* * * * *', new DateTimeImmutable('2025-01-01 10:00:00', $this->timezone)));
    }

    /**
     * @covers       \Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser::getNextRunDate
     * @dataProvider nextRunDateProvider
     *
     * @param non-empty-string $timeZone
     */
    public function testGetNextRunDate(string $expression, string $from, string $timeZone, string $expected): void
    {
        // The 'from' date is always assumed to be in UTC for provider consistency
        $fromDateTime = new DateTimeImmutable($from, $this->timezone);
        // The $timeZone parameter is the one we are testing (the one that would be on the Task)
        $nextRun = $this->parser->getNextRunDate($expression, $fromDateTime, $timeZone);
        // We compare the resulting time in UTC
        $nextRun->setTimezone($this->timezone);

        self::assertEquals($expected, $nextRun->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, string[]>
     */
    public static function nextRunDateProvider(): array
    {
        return [
            // 'expression', 'from (UTC)', 'taskTimeZone', 'expected (UTC)'
            'every minute UTC'                           => ['* * * * *', '2025-08-16 10:30:00', 'UTC', '2025-08-16 10:31:00'],
            'next hour UTC'                              => ['0 * * * *', '2025-08-16 10:30:00', 'UTC', '2025-08-16 11:00:00'],

            // Test Timezone: dailyAt('09:00') in New York (UTC-5 during this time)
            // 'from' date is 12:00 UTC (07:00 NY)
            // Next 09:00 NY is 14:00 UTC
            'specific timezone (NY Daily)'               => ['0 9 * * *', '2025-11-12 12:00:00', 'America/New_York', '2025-11-12 14:00:00'],

            // Test Timezone: dailyAt('02:00') in Berlin (UTC+1 during this time)
            // 'from' date is 00:00 UTC (01:00 Berlin)
            // Next 02:00 Berlin is 01:00 UTC
            'specific timezone (Berlin Daily)'           => ['0 2 * * *', '2025-11-12 00:00:00', 'Europe/Berlin', '2025-11-12 01:00:00'],

            // Test Timezone: dailyAt('02:00') in Berlin (UTC+1)
            // 'from' date is 01:30 UTC (02:30 Berlin)
            // Next 02:00 Berlin is 01:00 UTC *the next day*
            'specific timezone (Berlin Daily after run)' => ['0 2 * * *', '2025-11-12 01:30:00', 'Europe/Berlin', '2025-11-13 01:00:00'],
        ];
    }
}
