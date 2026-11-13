<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Event\IncidentExtended;
use Phil\IncidentMode\Event\IncidentLifted;
use Phil\IncidentMode\Event\IncidentStarted;
use Phil\IncidentMode\Exception\IncidentNotActive;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\Exception\UnknownPreset;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentMode\Tests\Fixture\LatestFirstStore;
use Phil\IncidentMode\Tests\Fixture\RacingStore;
use Phil\IncidentMode\Tests\Fixture\RecordingEvents;
use Phil\IncidentMode\Tests\Fixture\RecordingScheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(IncidentMode::class)]
#[CoversClass(IncidentStarted::class)]
#[CoversClass(IncidentExtended::class)]
#[CoversClass(IncidentLifted::class)]
#[CoversClass(IncidentNotActive::class)]
#[CoversClass(NoLiftScheduler::class)]
final class IncidentModeTest extends TestCase
{
    private MockClock $clock;

    private ActivationStore $store;

    private RecordingEvents $events;

    private RecordingScheduler $scheduler;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-13 10:00:00 UTC'));
        $this->store = new InMemoryActivationStore();
        $this->events = new RecordingEvents();
        $this->scheduler = new RecordingScheduler();
    }

    public function testAnIncidentIsStartedForTheDurationGiven(): void
    {
        $incidents = $this->incidents();
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(3), Actor::of('m-1'), 'Spam wave');

        self::assertSame('2026-11-13 13:00:00', $activation->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('m-1', $activation->startedBy?->id);
        self::assertSame('Spam wave', $activation->reason);
        self::assertSame($activation, $this->store->find($activation->id));
        self::assertSame([$activation], $this->scheduler->scheduled);
        self::assertCount(1, $this->events->events);
        self::assertInstanceOf(IncidentStarted::class, $this->events->events[0]);
        self::assertSame($activation, $this->events->events[0]->activation);
        self::assertEquals(Presets::defaults()->all(), $incidents->presets()->all());
    }

    public function testThePresetBoundsTheDuration(): void
    {
        $incidents = $this->incidents();
        $incidents->start(Presets::READ_ONLY, Duration::ofDays(2), null);

        $this->expectExceptionObject(InvalidIncident::because('The preset "read-only" lasts at most 172800 seconds; 172860 were asked.'));

        $incidents->start(Presets::READ_ONLY, Duration::ofSeconds(172_860), null);
    }

    public function testAnUnknownPresetStartsNothing(): void
    {
        $this->expectException(UnknownPreset::class);

        $this->incidents()->start('storm', Duration::ofHours(1), null);
    }

    public function testWhatIsSuspendedNow(): void
    {
        $incidents = $this->incidents();
        self::assertFalse($incidents->isSuspended('signups'));
        self::assertSame([], $incidents->suspended());

        $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $this->clock->sleep(60);
        $incidents->start(Presets::SUSPEND_CONTRIBUTIONS, Duration::ofHours(2), null);

        self::assertTrue($incidents->isSuspended('signups'));
        self::assertTrue($incidents->isSuspended(Restriction::named('uploads')));
        self::assertFalse($incidents->isSuspended('reactions'));
        self::assertSame(['signups', 'invitations', 'new-messages', 'new-posts', 'new-comments', 'uploads'], $incidents->suspended());

        $this->clock->sleep(3_600);

        self::assertCount(1, $incidents->inForceNow());
        self::assertTrue($incidents->isSuspended('signups'));

        $this->clock->sleep(3_600);

        self::assertSame([], $incidents->inForceNow());
        self::assertFalse($incidents->isSuspended('signups'));
    }

    public function testReadOnlySuspendsEverything(): void
    {
        $incidents = $this->incidents();
        $incidents->start(Presets::READ_ONLY, Duration::ofMinutes(30), null);

        self::assertTrue($incidents->isSuspended('my-own-thing'));
        self::assertSame(['read-only'], $incidents->suspended());
    }

    public function testTheIncidentsInForceAreInTheOrderTheyStarted(): void
    {
        $incidents = $this->incidents();
        $first = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $this->clock->sleep(60);
        $second = $incidents->start(Presets::SUSPEND_INVITATIONS, Duration::ofHours(1), null);

        self::assertSame([$first->id, $second->id], array_map(static fn (Activation $activation): string => $activation->id, $incidents->inForceNow()));
        self::assertSame(['signups', 'invitations'], $incidents->suspended());
    }

    public function testAnIncidentStartedLaterIsNotInForceYet(): void
    {
        $this->store->add(new Activation(str_repeat('a', 32), 'read-only', [Restriction::named('read-only')], new DateTimeImmutable('2026-11-13 11:00:00 UTC'), new DateTimeImmutable('2026-11-13 12:00:00 UTC'), null));

        self::assertSame([], $this->incidents()->inForceNow());
    }

    public function testAnIncidentIsLiftedByHand(): void
    {
        $incidents = $this->incidents();
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $this->clock->sleep(600);

        $lifted = $incidents->lift($activation->id, Actor::of('m-2'));

        self::assertSame('2026-11-13 10:10:00', $lifted->liftedAt?->format('Y-m-d H:i:s'));
        self::assertSame(LiftReason::Manual, $lifted->liftReason);
        self::assertSame('m-2', $lifted->liftedBy?->id);
        self::assertSame(LiftReason::Manual, $this->store->find($activation->id)?->liftReason);
        self::assertFalse($incidents->isSuspended('signups'));
        self::assertInstanceOf(IncidentLifted::class, $this->events->events[1]);
        self::assertSame($lifted, $this->events->events[1]->activation);

        $this->expectExceptionObject(IncidentNotActive::withId($activation->id));

        $incidents->lift($activation->id, null);
    }

    public function testAnIncidentPastItsEndIsNotLiftedByHand(): void
    {
        $incidents = $this->incidents();
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $this->clock->sleep(3_600);

        $this->expectException(IncidentNotActive::class);

        $incidents->lift($activation->id, null);
    }

    public function testAnUnknownIncidentIsNotLifted(): void
    {
        $this->expectException(IncidentNotActive::class);

        $this->incidents()->lift(str_repeat('a', 32), null);
    }

    public function testALiftingTheStoreRefusesIsNotAnnounced(): void
    {
        $incidents = $this->incidents(new RacingStore());
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);

        try {
            $incidents->lift($activation->id, null);
            self::fail('The lifting was announced.');
        } catch (IncidentNotActive) {
            self::assertCount(1, $this->events->events);
        }
    }

    public function testAnExtensionTheStoreRefusesIsNotAnnounced(): void
    {
        $incidents = $this->incidents(new RacingStore());
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);

        try {
            $incidents->extend($activation->id, Duration::ofHours(1), null);
            self::fail('The extension was announced.');
        } catch (IncidentNotActive) {
            self::assertCount(1, $this->events->events);
            self::assertCount(1, $this->scheduler->scheduled);
        }
    }

    public function testAnIncidentIsExtendedWithinItsPreset(): void
    {
        $incidents = $this->incidents();
        $activation = $incidents->start(Presets::READ_ONLY, Duration::ofHours(24), null);

        $extended = $incidents->extend($activation->id, Duration::ofHours(24), Actor::of('m-3'));

        self::assertSame('2026-11-15 10:00:00', $extended->endsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-15 10:00:00', $this->store->find($activation->id)?->endsAt->format('Y-m-d H:i:s'));
        self::assertSame([$activation, $extended], $this->scheduler->scheduled);
        $event = $this->events->events[1];
        self::assertInstanceOf(IncidentExtended::class, $event);
        self::assertSame($extended, $event->activation);
        self::assertSame($activation->endsAt, $event->previousEnd);
        self::assertSame('m-3', $event->by?->id);

        $this->expectExceptionObject(InvalidIncident::because(\sprintf('The incident "%s" cannot last longer than its preset allows.', $activation->id)));

        $incidents->extend($activation->id, Duration::ofMinutes(1), null);
    }

    public function testAnIncidentWhosePresetIsGoneIsNotExtended(): void
    {
        $quiet = Preset::of('quiet', 'Quiet', ['new-posts'], Duration::ofHours(1), Duration::ofHours(4));
        $activation = $this->incidents(presets: Presets::withDefaults([$quiet]))->start('quiet', Duration::ofHours(1), null);

        $this->expectException(InvalidIncident::class);

        $this->incidents()->extend($activation->id, Duration::ofHours(1), null);
    }

    public function testAnIncidentNotInForceIsNotExtended(): void
    {
        $this->expectException(IncidentNotActive::class);

        $this->incidents()->extend(str_repeat('a', 32), Duration::ofHours(1), null);
    }

    public function testAnIncidentPastItsEndIsLiftedOnceAtItsEnd(): void
    {
        $incidents = $this->incidents();
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);

        self::assertFalse($incidents->liftIfDue($activation->id));
        $this->clock->sleep(3_700);

        self::assertTrue($incidents->liftIfDue($activation->id));
        self::assertFalse($incidents->liftIfDue($activation->id));
        self::assertFalse($incidents->liftIfDue(str_repeat('f', 32)));

        $lifted = $this->store->find($activation->id);
        self::assertSame('2026-11-13 11:00:00', $lifted?->liftedAt?->format('Y-m-d H:i:s'));
        self::assertSame(LiftReason::Expired, $lifted->liftReason);
        self::assertNull($lifted->liftedBy);
        self::assertCount(2, $this->events->events);
        $event = $this->events->events[1];
        self::assertInstanceOf(IncidentLifted::class, $event);
        self::assertSame(LiftReason::Expired, $event->activation->liftReason);
        self::assertSame('2026-11-13 11:00:00', $event->activation->liftedAt?->format('Y-m-d H:i:s'));
    }

    public function testADueIncidentTheStoreRefusesToLiftIsNotAnnounced(): void
    {
        $incidents = $this->incidents(new RacingStore());
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $this->clock->sleep(3_600);

        self::assertFalse($incidents->liftIfDue($activation->id));
        self::assertCount(1, $this->events->events);
    }

    public function testEveryExpiredIncidentIsLifted(): void
    {
        $incidents = $this->incidents();
        $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $incidents->start(Presets::SUSPEND_INVITATIONS, Duration::ofHours(2), null);

        $kept = $incidents->start(Presets::SUSPEND_NEW_MESSAGES, Duration::ofHours(3), null);
        $this->clock->sleep(7_200);

        self::assertSame(1, $incidents->liftExpired(1));
        self::assertSame(1, $incidents->liftExpired());
        self::assertSame(0, $incidents->liftExpired());
        self::assertNull($this->store->find($kept->id)?->liftedAt);
    }

    public function testTheJournalIsTheLatestFirst(): void
    {
        $incidents = $this->incidents();
        $first = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);
        $this->clock->sleep(60);
        $second = $incidents->start(Presets::SUSPEND_INVITATIONS, Duration::ofHours(1), null);

        self::assertSame([$second->id, $first->id], array_map(static fn (Activation $activation): string => $activation->id, $incidents->journal()));
        self::assertSame([$first->id], array_map(static fn (Activation $activation): string => $activation->id, $incidents->journal(1, 1)));
        self::assertSame($first->id, $incidents->find($first->id)?->id);
    }

    public function testWithoutAScheduler(): void
    {
        $incidents = new IncidentMode(Presets::defaults(), $this->store, $this->clock);
        $activation = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);

        new NoLiftScheduler()->schedule($activation);

        self::assertTrue($incidents->isSuspended('signups'));
    }

    public function testWithoutADispatcherTheChangesAreMadeAllTheSame(): void
    {
        $incidents = new IncidentMode(Presets::defaults(), $this->store, $this->clock);
        $first = $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(2), null);
        $second = $incidents->start(Presets::READ_ONLY, Duration::ofHours(1), null);

        self::assertSame($first->endsAt->modify('+1 hour')->getTimestamp(), $incidents->extend($first->id, Duration::ofHours(1), null)->endsAt->getTimestamp());
        self::assertNotNull($incidents->lift($second->id, null)->liftedAt);

        $this->clock->sleep(4 * 3_600);

        self::assertTrue($incidents->liftIfDue($first->id));
    }

    public function testWhatIsInForceIsAListWithoutTheIncidentsStillToCome(): void
    {
        $this->store = new LatestFirstStore();
        $this->store->add(new Activation(str_repeat('a', 32), 'read-only', [Restriction::named('read-only')], new DateTimeImmutable('2026-11-13 11:00:00 UTC'), new DateTimeImmutable('2026-11-13 12:00:00 UTC'), null));
        $this->store->add(new Activation(str_repeat('b', 32), 'read-only', [Restriction::named('read-only')], new DateTimeImmutable('2026-11-13 09:00:00 UTC'), new DateTimeImmutable('2026-11-13 12:00:00 UTC'), null));

        $inForce = $this->incidents()->inForceNow();

        self::assertCount(1, $inForce);
        self::assertSame(str_repeat('b', 32), $inForce[0]->id);
    }

    private function incidents(?ActivationStore $store = null, ?Presets $presets = null): IncidentMode
    {
        if ($store instanceof ActivationStore) {
            $this->store = $store;
        }

        return new IncidentMode($presets ?? Presets::defaults(), $this->store, $this->clock, $this->events, $this->scheduler);
    }
}
