<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Application\Message\PurgeDeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Infrastructure\Scheduler\DeliveryAddressSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

#[CoversClass(DeliveryAddressSchedule::class)]
final class DeliveryAddressScheduleTest extends TestCase
{
    public function testItPurgesOnceADayByDefault(): void
    {
        $provider = new DeliveryAddressSchedule();
        $schedule = $provider->getSchedule();

        self::assertSame($schedule, $provider->getSchedule());
        [$recurring] = $schedule->getRecurringMessages();
        $messages = iterator_to_array($recurring->getMessages(new MessageContext('n', 'id', $recurring->getTrigger(), new DateTimeImmutable('2026-01-01'))), false);
        self::assertEquals([new PurgeDeliveryAddresses()], $messages);
        self::assertSame('every 1 day', (string) $recurring->getTrigger());
        self::assertNull($schedule->getLock());
    }

    public function testItRunsUnderALockWhenGivenAFactory(): void
    {
        $store = new InMemoryStore();
        $schedule = new DeliveryAddressSchedule('6 hours', new LockFactory($store))->getSchedule();

        [$recurring] = $schedule->getRecurringMessages();
        self::assertInstanceOf(TriggerInterface::class, $recurring->getTrigger());
        self::assertSame('every 6 hours', (string) $recurring->getTrigger());
        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse(new LockFactory($store)->createLock('scheduler_'.DeliveryAddressSchedule::NAME)->acquire());
        $lock->release();
    }
}
