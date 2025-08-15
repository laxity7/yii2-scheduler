<?php

namespace tests\unit;

use Laxity7\Yii2\Components\Scheduler\Components\Event;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testDailyAt(): void
    {
        $event = new Event('test/command');
        $event->dailyAt('14:35');
        $this->assertEquals('35 14 * * *', $event->expression);
    }

    public function testWithoutOverlapping(): void
    {
        $event = new Event('test/command');
        $this->assertFalse($event->withoutOverlapping);
        $event->withoutOverlapping();
        $this->assertTrue($event->withoutOverlapping);
    }
}
