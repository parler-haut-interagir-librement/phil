<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Unit;

use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\UsageMeter;
use Phil\UsageMeterBundle\Infrastructure\Console\PurgeUsageCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeUsageCommand::class)]
final class PurgeUsageCommandTest extends TestCase
{
    public function testTheCountersNoWindowCountsAreForgotten(): void
    {
        $clock = new MockClock('2026-03-14 10:30:00', 'UTC');
        $meter = new UsageMeter(new Meters(new Meter('exports', CalendarPeriod::day(), Limit::of(3))), new InMemoryUsageStore(), $clock);
        $meter->consume('alice', 'exports', 1, 'first');
        $meter->consume('bob', 'exports');

        $command = new PurgeUsageCommand($meter);
        $tester = new CommandTester($command);

        self::assertSame('phil:usage-meter:purge', $command->getName());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("0 counter(s) and key(s) forgotten.\n", $tester->getDisplay());

        $clock->modify('+1 day');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("3 counter(s) and key(s) forgotten.\n", $tester->getDisplay());
    }
}
