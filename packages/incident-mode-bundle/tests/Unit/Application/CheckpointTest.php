<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Exception\SuspendedByIncident;
use Phil\IncidentModeBundle\Application\Suspension;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\CountingStore;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Established;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Member;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Checkpoint::class)]
#[CoversClass(Suspension::class)]
#[CoversClass(SuspendedByIncident::class)]
final class CheckpointTest extends TestCase
{
    public function testNothingIsSuspendedWithoutAnIncident(): void
    {
        $stage = new Stage();

        self::assertFalse($stage->checkpoint->applies(Restriction::SIGNUPS));
        self::assertNull($stage->checkpoint->suspension(Restriction::SIGNUPS, Restriction::INVITATIONS));
        self::assertSame([], $stage->checkpoint->suspensions());
        $stage->checkpoint->guard(Restriction::SIGNUPS);
        self::assertSame([], $stage->established->asked);
    }

    public function testAnIncidentSuspendsWhatItNamesUntilItsEnd(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_SIGNUPS, '2h');

        self::assertTrue($stage->checkpoint->applies(Restriction::SIGNUPS));
        self::assertFalse($stage->checkpoint->applies(Restriction::INVITATIONS));
        self::assertNull($stage->checkpoint->suspension(Restriction::INVITATIONS));

        $suspension = $stage->checkpoint->suspension(Restriction::INVITATIONS, Restriction::SIGNUPS);
        self::assertInstanceOf(Suspension::class, $suspension);
        self::assertSame(Restriction::SIGNUPS, $suspension->restriction);
        self::assertSame('2026-11-17T12:00:00+00:00', $suspension->until->format(\DATE_ATOM));

        $stage->clock->modify('+2 hours');
        self::assertFalse($stage->checkpoint->applies(Restriction::SIGNUPS));
        self::assertNull($stage->checkpoint->suspension(Restriction::SIGNUPS));
    }

    public function testTheEndIsTheLatestOfTheIncidentsThatSuspendIt(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_SIGNUPS, '3h');
        $stage->start(Presets::SUSPEND_CONTRIBUTIONS, '1h');
        $stage->start(Presets::SUSPEND_INVITATIONS, '5h');

        self::assertSame('2026-11-17T13:00:00+00:00', $stage->checkpoint->suspension(Restriction::SIGNUPS)?->until->format(\DATE_ATOM));
        self::assertSame('2026-11-17T15:00:00+00:00', $stage->checkpoint->suspension(Restriction::INVITATIONS)?->until->format(\DATE_ATOM));
        self::assertSame('2026-11-17T11:00:00+00:00', $stage->checkpoint->suspension(Restriction::UPLOADS)?->until->format(\DATE_ATOM));
    }

    public function testTheFirstRestrictionThatAppliesIsTheOneTold(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_CONTRIBUTIONS, '1h');

        self::assertSame(Restriction::INVITATIONS, $stage->checkpoint->suspension(Restriction::REACTIONS, Restriction::INVITATIONS, Restriction::SIGNUPS)?->restriction);
    }

    public function testReadOnlySuspendsEverything(): void
    {
        $stage = new Stage();
        $stage->start(Presets::READ_ONLY, '30m');

        self::assertTrue($stage->checkpoint->applies('new-listings'));
        self::assertSame('new-listings', $stage->checkpoint->suspension('new-listings')?->restriction);
        self::assertSame([Restriction::READ_ONLY], array_map(static fn (Suspension $suspension): string => $suspension->restriction, $stage->checkpoint->suspensions()));
    }

    public function testEveryRestrictionInForceIsListedOnceInTheOrderTheIncidentsStarted(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_INVITATIONS, '5h');
        $stage->clock->modify('+1 minute');
        $stage->start(Presets::SUSPEND_CONTRIBUTIONS, '1h');

        $suspensions = $stage->checkpoint->suspensions();
        self::assertSame([Restriction::INVITATIONS, Restriction::SIGNUPS, Restriction::NEW_MESSAGES, Restriction::NEW_POSTS, Restriction::NEW_COMMENTS, Restriction::UPLOADS], array_map(static fn (Suspension $suspension): string => $suspension->restriction, $suspensions));
        self::assertSame('2026-11-17T15:00:00+00:00', $suspensions[0]->until->format(\DATE_ATOM));
        self::assertSame('2026-11-17T11:01:00+00:00', $suspensions[1]->until->format(\DATE_ATOM));
    }

    public function testAnEstablishedMemberIsSparedFromTheRestrictionsConfigured(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_CONTRIBUTIONS, '1h');

        $stage->member->id = 'ada';
        self::assertFalse($stage->checkpoint->applies(Restriction::NEW_POSTS));
        self::assertTrue($stage->checkpoint->applies(Restriction::NEW_COMMENTS));
        self::assertNull($stage->checkpoint->suspension(Restriction::NEW_POSTS));
        self::assertNotContains(Restriction::NEW_POSTS, array_map(static fn (Suspension $suspension): string => $suspension->restriction, $stage->checkpoint->suspensions()));

        $stage->member->id = 'bob';
        self::assertTrue($stage->checkpoint->applies(Restriction::NEW_POSTS));

        $stage->member->id = null;
        self::assertTrue($stage->checkpoint->applies(Restriction::NEW_POSTS));
        self::assertContains('ada', $stage->established->asked);
        self::assertContains('bob', $stage->established->asked);
    }

    public function testAGuardRefusesWithTheStatusAndWhenToComeBack(): void
    {
        $stage = new Stage(status: 429);
        $stage->start(Presets::SUSPEND_SIGNUPS, '2h');
        $stage->clock->modify('+30 minutes');

        try {
            $stage->checkpoint->guard(Restriction::INVITATIONS, Restriction::SIGNUPS);
            self::fail('The guard refuses while sign-ups are suspended.');
        } catch (SuspendedByIncident $suspendedByIncident) {
            self::assertSame(429, $suspendedByIncident->getStatusCode());
            self::assertSame(5_400, $suspendedByIncident->retryAfter);
            self::assertSame(['Retry-After' => '5400'], $suspendedByIncident->getHeaders());
            self::assertSame(Restriction::SIGNUPS, $suspendedByIncident->suspension->restriction);
            self::assertSame('"signups" is suspended by an incident until 2026-11-17T12:00:00+00:00.', $suspendedByIncident->getMessage());
        }

        $stage->checkpoint->guard(Restriction::INVITATIONS);
    }

    public function testTheStatusIs503ByDefault(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_SIGNUPS, '2h');

        $this->expectExceptionObject(new SuspendedByIncident(new Suspension(Restriction::SIGNUPS, new DateTimeImmutable('2026-11-17 12:00 UTC')), 503, 7_200));
        $stage->checkpoint->guard(Restriction::SIGNUPS);
    }

    public function testADefaultStatusIsServiceUnavailable(): void
    {
        self::assertSame(503, Checkpoint::DEFAULT_STATUS);
    }

    public function testAVisitorIsAnsweredFromOneReadOfTheIncidentsInForce(): void
    {
        $store = new CountingStore(new InMemoryActivationStore());
        $clock = new MockClock('2026-11-17 10:00:00 UTC');
        $incidents = new IncidentMode(Presets::defaults(), $store, $clock);
        $member = new Member();
        $checkpoint = new Checkpoint($incidents, new IncidentRestrictions($incidents, new Established(['ada']), [Restriction::NEW_POSTS]), $member, $clock);
        $incidents->start(Presets::SUSPEND_CONTRIBUTIONS, Duration::ofHours(1), null);

        self::assertCount(6, $checkpoint->suspensions());
        self::assertSame(1, $store->reads);
        self::assertSame(Restriction::SIGNUPS, $checkpoint->suspension(Restriction::REACTIONS, Restriction::SIGNUPS)?->restriction);
        self::assertSame(2, $store->reads);
        self::assertNull($checkpoint->suspension());
        self::assertSame(2, $store->reads);

        $member->id = 'ada';
        self::assertCount(5, $checkpoint->suspensions());
        self::assertGreaterThan(3, $store->reads);
    }

    public function testAnInvalidNameIsRefused(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_SIGNUPS);

        $this->expectException(InvalidIncident::class);
        $stage->checkpoint->applies('Sign Ups');
    }

    public function testASuspensionCountsTheSecondsToItsEndAtLeastOne(): void
    {
        $suspension = new Suspension(Restriction::SIGNUPS, new DateTimeImmutable('2026-11-17 12:00:00 UTC'));

        self::assertSame(3_600, $suspension->secondsFrom(new DateTimeImmutable('2026-11-17 11:00:00 UTC')));
        self::assertSame(1, $suspension->secondsFrom(new DateTimeImmutable('2026-11-17 11:59:59 UTC')));
        self::assertSame(1, $suspension->secondsFrom(new DateTimeImmutable('2026-11-17 12:00:00 UTC')));
        self::assertSame(1, $suspension->secondsFrom(new DateTimeImmutable('2026-11-17 13:00:00 UTC')));
    }
}
