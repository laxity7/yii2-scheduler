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
     */
    public function testGetNextRunDate(string $expression, string $from, string $expected): void
    {
        $nextRun = $this->parser->getNextRunDate($expression, new DateTimeImmutable($from, $this->timezone));
        self::assertEquals($expected, $nextRun->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, string[]>
     */
    public static function nextRunDateProvider(): array
    {
        return [
            'every minute' => ['* * * * *', '2025-08-16 10:30:00', '2025-08-16 10:31:00'],
            'next hour'    => ['0 * * * *', '2025-08-16 10:30:00', '2025-08-16 11:00:00'],
        ];
    }
}
