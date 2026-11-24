<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Infrastructure\Scheduler;

use DateTimeImmutable;
use Phil\SchedulingPollBundle\Application\Message\CloseDuePolls;
use Phil\SchedulingPollBundle\Application\Message\EraseRespondents;
use Phil\SchedulingPollBundle\Infrastructure\Scheduler\SchedulingPollSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

#[CoversClass(SchedulingPollSchedule::class)]
final class SchedulingPollScheduleTest extends TestCase
{
    public function testTheScheduleClosesAndErasesAtItsFrequenciesWithAStateAndALock(): void
    {
        $cache = new ArrayAdapter();
        $schedule = new SchedulingPollSchedule($cache, new LockFactory(new InMemoryStore()), '10 minutes', '2 hours')->getSchedule();
        [$close, $erase] = array_values($schedule->getRecurringMessages());

        self::assertEquals([new CloseDuePolls()], iterator_to_array($close->getProvider()->getMessages(new MessageContext('n', 'i', $close->getTrigger(), new DateTimeImmutable()))));
        self::assertInstanceOf(PeriodicalTrigger::class, $close->getTrigger());
        self::assertSame('every 10 minutes', (string) $close->getTrigger());
        self::assertSame('every 2 hours', (string) $erase->getTrigger());
        self::assertEquals([new EraseRespondents()], iterator_to_array($erase->getProvider()->getMessages(new MessageContext('n', 'i', $erase->getTrigger(), new DateTimeImmutable()))));
        self::assertSame($cache, $schedule->getState());
        self::assertNotNull($schedule->getLock());
        self::assertSame('phil_scheduling_poll', SchedulingPollSchedule::NAME);
    }

    public function testWithoutALockFactoryTheScheduleHoldsNoLock(): void
    {
        $schedule = new SchedulingPollSchedule(new ArrayAdapter())->getSchedule();

        self::assertNull($schedule->getLock());
        self::assertSame(['every 5 minutes', 'every 1 hour'], array_map(static fn (RecurringMessage $message): string => (string) $message->getTrigger(), array_values($schedule->getRecurringMessages())));
    }
}
