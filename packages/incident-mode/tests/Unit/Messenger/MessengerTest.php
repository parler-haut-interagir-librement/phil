<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit\Messenger;

use DateTimeImmutable;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncident;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Tests\Fixture\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(MessengerLiftScheduler::class)]
#[CoversClass(LiftIncident::class)]
#[CoversClass(LiftIncidentHandler::class)]
#[CoversClass(LiftExpiredIncidents::class)]
#[CoversClass(LiftExpiredIncidentsHandler::class)]
final class MessengerTest extends TestCase
{
    public function testTheLiftingIsSentDelayedUntilTheEndAndLiftsOnceDue(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-13 10:00:00 UTC'));
        $bus = new RecordingBus();
        $store = new InMemoryActivationStore();
        $incidents = new IncidentMode(Presets::defaults(), $store, $clock, scheduler: new MessengerLiftScheduler($bus, $clock));

        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);

        self::assertCount(1, $bus->sent);
        $message = $bus->sent[0]->getMessage();
        self::assertInstanceOf(LiftIncident::class, $message);
        self::assertSame($activation->id, $message->id);
        self::assertSame(3_600_000, $bus->sent[0]->last(DelayStamp::class)?->getDelay());

        $handler = new LiftIncidentHandler($incidents);
        $handler($message);
        self::assertNull($store->find($activation->id)?->liftedAt);

        $clock->sleep(3_600);
        $handler($message);
        self::assertSame(LiftReason::Expired, $store->find($activation->id)?->liftReason);

        $clock->sleep(7_200);
        new MessengerLiftScheduler($bus, $clock)->schedule($activation);
        $late = new RecordingBus();
        new MessengerLiftScheduler($late, $clock)->schedule($activation);
        self::assertSame(0, $late->sent[0]->last(DelayStamp::class)?->getDelay());
    }

    public function testTheSweepLiftsEveryExpiredIncident(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-13 10:00:00 UTC'));
        $store = new InMemoryActivationStore();
        $incidents = new IncidentMode(Presets::defaults(), $store, $clock);
        $first = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $second = $incidents->start(Presets::SUSPEND_INVITATIONS, Duration::ofHours(1), null);
        $clock->sleep(3_600);

        new LiftExpiredIncidentsHandler($incidents)(new LiftExpiredIncidents());

        self::assertSame(LiftReason::Expired, $store->find($first->id)?->liftReason);
        self::assertSame(LiftReason::Expired, $store->find($second->id)?->liftReason);
    }
}
