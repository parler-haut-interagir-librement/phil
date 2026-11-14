<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit;

use DateTimeImmutable;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\NoEstablishedMembers;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentMode\Tests\Fixture\Established;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(IncidentRestrictions::class)]
#[CoversClass(NoEstablishedMembers::class)]
final class IncidentRestrictionsTest extends TestCase
{
    public function testNothingAppliesWithoutAnIncident(): void
    {
        $established = new Established(['old-timer']);
        $restrictions = new IncidentRestrictions($this->incidents(), $established, [Restriction::NEW_POSTS]);

        self::assertFalse($restrictions->appliesTo(Restriction::NEW_POSTS, 'newcomer'));
        self::assertFalse($restrictions->appliesTo(Restriction::NEW_POSTS, null));
        self::assertSame([], $established->asked, 'nobody is looked up while nothing is suspended');
    }

    public function testAnIncidentSparesTheEstablishedMembersFromTheRestrictionsListed(): void
    {
        $incidents = $this->incidents();
        $established = new Established(['old-timer']);
        $restrictions = new IncidentRestrictions($incidents, $established, [Restriction::NEW_POSTS, Restriction::REACTIONS]);
        $incidents->start(Presets::SUSPEND_CONTRIBUTIONS, Duration::ofHours(1), null);

        self::assertTrue($restrictions->appliesTo(Restriction::NEW_POSTS, 'newcomer'));
        self::assertFalse($restrictions->appliesTo(Restriction::NEW_POSTS, 'old-timer'));
        self::assertSame(['newcomer', 'old-timer'], $established->asked);

        self::assertTrue($restrictions->appliesTo(Restriction::NEW_POSTS, null), 'a visitor is never spared');
        self::assertTrue($restrictions->appliesTo(Restriction::NEW_COMMENTS, 'old-timer'), 'a restriction not listed spares nobody');
        self::assertFalse($restrictions->appliesTo(Restriction::REACTIONS, 'old-timer'), 'a restriction listed but not suspended applies to nobody');
        self::assertSame(['newcomer', 'old-timer'], $established->asked, 'nobody is looked up when the answer does not depend on it');
    }

    public function testReadOnlySparesOnlyWhenItIsListedToo(): void
    {
        $incidents = $this->incidents();
        $established = new Established(['old-timer']);
        $incidents->start(Presets::READ_ONLY, Duration::ofHours(1), null);

        self::assertTrue(new IncidentRestrictions($incidents, $established, [Restriction::NEW_POSTS])->appliesTo(Restriction::NEW_POSTS, 'old-timer'));
        self::assertFalse(new IncidentRestrictions($incidents, $established, [Restriction::NEW_POSTS, Restriction::READ_ONLY])->appliesTo(Restriction::NEW_POSTS, 'old-timer'));
        self::assertTrue(new IncidentRestrictions($incidents, $established, [Restriction::NEW_POSTS, Restriction::READ_ONLY])->appliesTo(Restriction::NEW_POSTS, 'newcomer'));
        self::assertFalse(new IncidentRestrictions($incidents, $established, [Restriction::READ_ONLY])->appliesTo(Restriction::READ_ONLY, 'old-timer'));
        self::assertTrue(new IncidentRestrictions($incidents, $established, [Restriction::READ_ONLY])->appliesTo(Restriction::NEW_POSTS, 'old-timer'), 'sparing read-only alone spares nothing else');
        self::assertTrue(new IncidentRestrictions($incidents, $established)->appliesTo(Restriction::READ_ONLY, 'old-timer'));
    }

    public function testNobodyIsEstablishedByDefault(): void
    {
        $incidents = $this->incidents();
        $incidents->start(Presets::SUSPEND_SIGNUPS, Duration::ofHours(1), null);

        $restrictions = new IncidentRestrictions($incidents, new NoEstablishedMembers(), [Restriction::SIGNUPS]);

        self::assertFalse(new NoEstablishedMembers()->isEstablished('old-timer'));
        self::assertTrue($restrictions->appliesTo(Restriction::SIGNUPS, 'old-timer'));
    }

    public function testASparedRestrictionIsAName(): void
    {
        $this->expectExceptionObject(InvalidIncident::because('"New posts" is not a restriction name: a lowercase letter, then up to 63 lowercase letters, digits and hyphens.'));

        new IncidentRestrictions($this->incidents(), new NoEstablishedMembers(), [Restriction::NEW_POSTS, 'New posts']);
    }

    public function testTheRestrictionAskedAboutIsAName(): void
    {
        $restrictions = new IncidentRestrictions($this->incidents(), new NoEstablishedMembers());

        $this->expectException(InvalidIncident::class);

        $restrictions->appliesTo('New posts', 'old-timer');
    }

    private function incidents(): IncidentMode
    {
        return new IncidentMode(Presets::defaults(), new InMemoryActivationStore(), new MockClock(new DateTimeImmutable('2026-11-14 10:00:00 UTC')));
    }
}
