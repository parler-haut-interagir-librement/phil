<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTracking\Tests\Unit\Fixture\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorGroup::class)]
#[CoversClass(GroupStatus::class)]
#[CoversClass(Occurrence::class)]
final class GroupTest extends TestCase
{
    public function testTheFirstEventOpensAGroup(): void
    {
        $group = ErrorGroup::first('f', Events::one());

        self::assertSame('f', $group->fingerprint);
        self::assertSame('RuntimeException: User 42 not found', $group->title);
        self::assertSame('App\Export::run (src/Export.php:20)', $group->culprit);
        self::assertSame(GroupStatus::Open, $group->status);
        self::assertSame(1, $group->timesSeen);
        self::assertEquals(new DateTimeImmutable('2026-11-08T10:00:00+00:00'), $group->firstSeen);
        self::assertEquals(new DateTimeImmutable('2026-11-08T10:00:00+00:00'), $group->lastSeen);
        self::assertSame('1.0.0', $group->lastRelease);
        self::assertNull($group->resolvedRelease);
        self::assertNull(ErrorGroup::first('f', new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Http, Severity::Error, [new ExceptionRecord('E', '')]))->culprit);
    }

    public function testAnotherEventIsCounted(): void
    {
        $group = ErrorGroup::first('f', Events::one())->seen(Events::one('User 43 not found', '1.1.0', '2026-11-09T10:00:00+00:00'));

        self::assertSame(2, $group->timesSeen);
        self::assertSame('RuntimeException: User 42 not found', $group->title);
        self::assertSame('App\Export::run (src/Export.php:20)', $group->culprit);
        self::assertSame('f', $group->fingerprint);
        self::assertSame(GroupStatus::Open, $group->status);
        self::assertEquals(new DateTimeImmutable('2026-11-08T10:00:00+00:00'), $group->firstSeen);
        self::assertEquals(new DateTimeImmutable('2026-11-09T10:00:00+00:00'), $group->lastSeen);
        self::assertSame('1.1.0', $group->lastRelease);

        $late = $group->seen(Events::one(release: null, at: '2026-11-07T10:00:00+00:00'));
        self::assertEquals(new DateTimeImmutable('2026-11-09T10:00:00+00:00'), $late->lastSeen);
        self::assertSame('1.1.0', $late->lastRelease);
    }

    public function testAResolvedGroupRegressesOnAnotherRelease(): void
    {
        $resolved = ErrorGroup::first('f', Events::one())->resolve();

        self::assertSame(GroupStatus::Resolved, $resolved->status);
        self::assertSame('1.0.0', $resolved->resolvedRelease);
        self::assertSame(GroupStatus::Resolved, $resolved->seen(Events::one())->status);
        self::assertSame('1.0.0', $resolved->seen(Events::one())->resolvedRelease);

        $regressed = $resolved->seen(Events::one(release: '1.1.0'));
        self::assertSame(GroupStatus::Regressed, $regressed->status);
        self::assertNull($regressed->resolvedRelease);
        self::assertSame(GroupStatus::Regressed, $resolved->seen(Events::one(release: null))->status);
        self::assertSame(GroupStatus::Regressed, $regressed->seen(Events::one(release: '1.1.0'))->status);
    }

    public function testAGroupResolvedWithoutAReleaseRegressesOnAnyEvent(): void
    {
        $resolved = ErrorGroup::first('f', Events::one(release: null))->resolve();

        self::assertNull($resolved->resolvedRelease);
        self::assertSame(GroupStatus::Regressed, $resolved->seen(Events::one(release: null))->status);
        self::assertSame(GroupStatus::Regressed, $resolved->seen(Events::one(release: '1.0.0'))->status);
    }

    public function testAnIgnoredGroupStaysIgnored(): void
    {
        $ignored = ErrorGroup::first('f', Events::one())->resolve()->ignore();

        self::assertSame(GroupStatus::Ignored, $ignored->status);
        self::assertNull($ignored->resolvedRelease);
        self::assertSame(GroupStatus::Ignored, $ignored->seen(Events::one(release: '2.0.0'))->status);
        self::assertSame(2, $ignored->seen(Events::one())->timesSeen);
    }

    public function testAGroupIsReopened(): void
    {
        $group = ErrorGroup::first('f', Events::one())->seen(Events::one(at: '2026-11-09T10:00:00+00:00'));
        $reopened = $group->resolve()->reopen();

        self::assertSame(GroupStatus::Open, $reopened->status);
        self::assertNull($reopened->resolvedRelease);
        self::assertSame([$group->fingerprint, $group->title, $group->culprit, $group->timesSeen, $group->firstSeen, $group->lastSeen, $group->lastRelease], [$reopened->fingerprint, $reopened->title, $reopened->culprit, $reopened->timesSeen, $reopened->firstSeen, $reopened->lastSeen, $reopened->lastRelease]);
    }

    #[TestWith([GroupStatus::Open, true])]
    #[TestWith([GroupStatus::Regressed, true])]
    #[TestWith([GroupStatus::Resolved, false])]
    #[TestWith([GroupStatus::Ignored, false])]
    public function testUnresolvedStatuses(GroupStatus $status, bool $unresolved): void
    {
        self::assertSame($unresolved, $status->isUnresolved());
    }

    #[TestWith([Occurrence::First, true])]
    #[TestWith([Occurrence::Regression, true])]
    #[TestWith([Occurrence::Repeat, false])]
    public function testOccurrencesThatAlert(Occurrence $occurrence, bool $alerts): void
    {
        self::assertSame($alerts, $occurrence->alerts());
    }
}
