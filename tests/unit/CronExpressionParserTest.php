<?php

namespace tests\unit;

use DateTime;
use Laxity7\Yii2\Components\Scheduler\Components\CronExpressionParser;
use PHPUnit\Framework\TestCase;

class CronExpressionParserTest extends TestCase
{
    private CronExpressionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CronExpressionParser();
    }

    public function testEveryMinute(): void
    {
        $this->assertTrue($this->parser->isDue('* * * * *', new DateTime('2025-01-01 10:00:00')));
    }

    public function testSpecificMinute(): void
    {
        $this->assertTrue($this->parser->isDue('5 * * * *', new DateTime('2025-01-01 10:05:00')));
        $this->assertFalse($this->parser->isDue('5 * * * *', new DateTime('2025-01-01 10:06:00')));
    }
}
