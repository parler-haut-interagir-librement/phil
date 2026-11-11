<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use DateTimeImmutable;
use Phil\Listings\Exception\InvalidListing;
use Phil\Listings\Exception\UnknownKind;
use Phil\Listings\KindPolicies;
use Phil\Listings\KindPolicy;
use Phil\Listings\ListingKind;
use Phil\Listings\Moderation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(KindPolicy::class)]
#[CoversClass(KindPolicies::class)]
#[CoversClass(UnknownKind::class)]
final class KindPolicyTest extends TestCase
{
    public function testThePolicyHasSafeDefaults(): void
    {
        $policy = KindPolicy::of(ListingKind::of('classified'));

        self::assertSame('classified', $policy->kind->value);
        self::assertSame(30, $policy->lifetimeDays);
        self::assertSame(3, $policy->reminderDays);
        self::assertSame(30, $policy->extensionDays);
        self::assertSame(3, $policy->maxExtensions);
        self::assertSame(Moderation::AfterPublication, $policy->moderation);
        self::assertSame(3, $policy->reportsToHide);
        self::assertNull($policy->minimumAge);
        self::assertTrue($policy->acceptsResponses);
        self::assertSame(100, $policy->maxOpenResponses);
    }

    public function testEveryRuleIsKept(): void
    {
        $policy = KindPolicy::of(ListingKind::of('job'), 60, 7, 15, 2, Moderation::BeforePublication, 5, 18, false, 20);

        self::assertSame(60, $policy->lifetimeDays);
        self::assertSame(7, $policy->reminderDays);
        self::assertSame(15, $policy->extensionDays);
        self::assertSame(2, $policy->maxExtensions);
        self::assertSame(Moderation::BeforePublication, $policy->moderation);
        self::assertSame(5, $policy->reportsToHide);
        self::assertSame(18, $policy->minimumAge);
        self::assertFalse($policy->acceptsResponses);
        self::assertSame(20, $policy->maxOpenResponses);
    }

    public function testTheBoundsAreInclusive(): void
    {
        $low = KindPolicy::of(ListingKind::of('a'), lifetimeDays: 1, reminderDays: 0, extensionDays: 1, maxExtensions: 0, reportsToHide: 1, minimumAge: 0, maxOpenResponses: 1);
        $high = KindPolicy::of(ListingKind::of('a'), lifetimeDays: 365, reminderDays: 364, extensionDays: 365, maxExtensions: 52, reportsToHide: 100, minimumAge: 120, maxOpenResponses: 10000);

        self::assertSame([1, 0, 1, 0, 1, 0, 1], [$low->lifetimeDays, $low->reminderDays, $low->extensionDays, $low->maxExtensions, $low->reportsToHide, $low->minimumAge, $low->maxOpenResponses]);
        self::assertSame([365, 364, 365, 52, 100, 120, 10000], [$high->lifetimeDays, $high->reminderDays, $high->extensionDays, $high->maxExtensions, $high->reportsToHide, $high->minimumAge, $high->maxOpenResponses]);
    }

    /**
     * @param array<string, int> $rule
     */
    #[TestWith([['lifetimeDays' => 0], 'The lifetime in days is from 1 to 365; 0 is not.'])]
    #[TestWith([['lifetimeDays' => 366], 'The lifetime in days is from 1 to 365; 366 is not.'])]
    #[TestWith([['reminderDays' => -1], 'The reminder in days is from 0 to 29; -1 is not.'])]
    #[TestWith([['reminderDays' => 30], 'The reminder in days is from 0 to 29; 30 is not.'])]
    #[TestWith([['extensionDays' => 0], 'The extension in days is from 1 to 365; 0 is not.'])]
    #[TestWith([['extensionDays' => 366], 'The extension in days is from 1 to 365; 366 is not.'])]
    #[TestWith([['maxExtensions' => -1], 'The number of extensions is from 0 to 52; -1 is not.'])]
    #[TestWith([['maxExtensions' => 53], 'The number of extensions is from 0 to 52; 53 is not.'])]
    #[TestWith([['reportsToHide' => 0], 'The number of reports that hide a listing is from 1 to 100; 0 is not.'])]
    #[TestWith([['reportsToHide' => 101], 'The number of reports that hide a listing is from 1 to 100; 101 is not.'])]
    #[TestWith([['minimumAge' => -1], 'The minimum age is from 0 to 120; -1 is not.'])]
    #[TestWith([['minimumAge' => 121], 'The minimum age is from 0 to 120; 121 is not.'])]
    #[TestWith([['maxOpenResponses' => 0], 'The number of open responses is from 1 to 10000; 0 is not.'])]
    #[TestWith([['maxOpenResponses' => 10001], 'The number of open responses is from 1 to 10000; 10001 is not.'])]
    public function testARuleOutOfItsBoundsIsRefused(array $rule, string $message): void
    {
        $this->expectExceptionObject(InvalidListing::because($message));

        KindPolicy::of(ListingKind::of('a'), ...$rule);
    }

    public function testTheDatesAreCountedInDays(): void
    {
        $policy = KindPolicy::of(ListingKind::of('a'), lifetimeDays: 30, reminderDays: 3, extensionDays: 10);
        $start = new DateTimeImmutable('2026-03-01T12:00:00+00:00');

        self::assertSame('2026-03-31T12:00:00+00:00', $policy->expiryFrom($start)->format(\DATE_ATOM));
        self::assertSame('2026-03-11T12:00:00+00:00', $policy->extendedFrom($start)->format(\DATE_ATOM));
        self::assertSame('2026-02-26T12:00:00+00:00', $policy->reminderFrom($start)->format(\DATE_ATOM));
    }

    public function testThePoliciesAreKeptByKind(): void
    {
        $job = KindPolicy::of(ListingKind::of('job'));
        $classified = KindPolicy::of(ListingKind::of('classified'));
        $policies = new KindPolicies([$job, $classified]);

        self::assertSame($job, $policies->get(ListingKind::of('job')));
        self::assertTrue($policies->has(ListingKind::of('classified')));
        self::assertFalse($policies->has(ListingKind::of('partner')));
        self::assertSame(['job', 'classified'], array_map(static fn (ListingKind $kind): string => $kind->value, $policies->kinds()));

        self::assertSame('No policy is declared for the kind of listing "partner".', UnknownKind::named(ListingKind::of('partner'))->getMessage());
        $this->expectExceptionObject(UnknownKind::named(ListingKind::of('partner')));

        $policies->get(ListingKind::of('partner'));
    }

    public function testAKindIsDeclaredOnce(): void
    {
        $this->expectExceptionObject(InvalidListing::because('The kind of listing "job" is declared twice.'));

        new KindPolicies([KindPolicy::of(ListingKind::of('job')), KindPolicy::of(ListingKind::of('job'))]);
    }
}
