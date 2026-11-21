<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Retention;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriodKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionPeriod::class)]
final class RetentionPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function cutoffs(): iterable
    {
        yield 'days' => ['P90D', '2026-10-31T12:00:00+00:00', '2026-08-02T12:00:00+00:00'];
        yield 'months' => ['P24M', '2026-10-31T12:00:00+00:00', '2024-10-31T12:00:00+00:00'];
        yield 'years' => ['P7Y', '2026-10-31T00:00:00+00:00', '2019-10-31T00:00:00+00:00'];
        yield 'minutes' => ['PT15M', '2026-10-31T00:10:00+00:00', '2026-10-30T23:55:00+00:00'];
        yield 'weeks' => ['P1W', '2026-10-31T00:00:00+00:00', '2026-10-24T00:00:00+00:00'];
        yield 'month crossing a year' => ['P3M', '2026-01-15T00:00:00+00:00', '2025-10-15T00:00:00+00:00'];
        yield 'months then days' => ['P1M2D', '2026-03-31T00:00:00+00:00', '2026-02-26T00:00:00+00:00'];
    }

    #[DataProvider('cutoffs')]
    public function testAFixedPeriodYieldsItsCutoff(string $duration, string $now, string $cutoff): void
    {
        self::assertSame($cutoff, RetentionPeriod::fixed($duration)->cutoff(new DateTimeImmutable($now))?->format(\DATE_ATOM));
    }

    /**
     * PHP's own arithmetic gives 3 March, which would purge records three days before their month is up.
     */
    public function testAMonthBackFromTheThirtyFirstClampsToTheEndOfFebruary(): void
    {
        $period = RetentionPeriod::fixed('P1M');

        self::assertSame('2026-02-28', $period->cutoff(new DateTimeImmutable('2026-03-31T00:00:00Z'))?->format('Y-m-d'));
        self::assertSame('2028-02-29', $period->cutoff(new DateTimeImmutable('2028-03-31T00:00:00Z'))?->format('Y-m-d'));
    }

    public function testAYearBackFromALeapDayClampsToTheTwentyEighth(): void
    {
        self::assertSame('2027-02-28', RetentionPeriod::fixed('P1Y')->cutoff(new DateTimeImmutable('2028-02-29T00:00:00Z'))?->format('Y-m-d'));
    }

    public function testTheCutoffIsInUtcWhateverTheClockZone(): void
    {
        $now = new DateTimeImmutable('2026-11-01T01:30:00', new DateTimeZone('America/Toronto'));
        $cutoff = RetentionPeriod::fixed('P1D')->cutoff($now);

        self::assertSame('2026-10-31T05:30:00+00:00', $cutoff?->format(\DATE_ATOM));
    }

    public function testOnlyAFixedPeriodHasACutoff(): void
    {
        $now = new DateTimeImmutable();

        self::assertNull(RetentionPeriod::untilEvent('life of the account')->cutoff($now));
        self::assertNull(RetentionPeriod::indefinite()->cutoff($now));
        self::assertNull(RetentionPeriod::notYetFixed('thresholds, invitation retention')->cutoff($now));
    }

    public function testEachShapeKeepsItsKindAndNote(): void
    {
        self::assertSame(RetentionPeriodKind::Fixed, RetentionPeriod::fixed('P90D')->kind);
        self::assertSame('P90D', RetentionPeriod::fixed('P90D')->duration);
        self::assertTrue(RetentionPeriod::fixed('P90D')->isFixed());
        self::assertSame('life of the account', RetentionPeriod::untilEvent(' life of the account ')->note);
        self::assertFalse(RetentionPeriod::indefinite()->isFixed());
        self::assertSame(RetentionPeriodKind::NotYetFixed, RetentionPeriod::notYetFixed('thresholds')->kind);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notADuration(): iterable
    {
        yield 'empty' => [''];
        yield 'bare P' => ['P'];
        yield 'bare T' => ['PT'];
        yield 'words' => ['90 days'];
        yield 'negative' => ['-P1D'];
        yield 'zero' => ['P0D'];
        yield 'zero time' => ['PT0S'];
    }

    /**
     * Each pair of adjacent units sums to a positive duration, even when the two are equal.
     *
     * @return iterable<string, array{string}>
     */
    public static function mixedUnits(): iterable
    {
        yield 'years and months' => ['P1Y1M'];
        yield 'months and days' => ['P1M1D'];
        yield 'days and hours' => ['P1DT1H'];
        yield 'hours and minutes' => ['PT1H1M'];
        yield 'minutes and seconds' => ['PT1M1S'];
    }

    #[DataProvider('mixedUnits')]
    public function testADurationOfSeveralUnitsIsPositive(string $duration): void
    {
        self::assertSame($duration, RetentionPeriod::fixed($duration)->duration);
    }

    /**
     * PHP reads the alternative ISO 8601 form; a retention schedule writes the designator form only.
     */
    public function testTheAlternativeFormIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        RetentionPeriod::fixed('P0001-00-00T00:00:00');
    }

    #[DataProvider('notADuration')]
    public function testAnythingButAPositiveIsoDurationIsRefused(string $duration): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        RetentionPeriod::fixed($duration);
    }

    public function testAnEventPeriodMustNameItsEvent(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        RetentionPeriod::untilEvent('  ');
    }

    public function testABlankMustNameWhereItWillBeDecided(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        RetentionPeriod::notYetFixed('');
    }

    /**
     * @return iterable<string, array{RetentionPeriod, bool}>
     */
    public static function againstFiveYears(): iterable
    {
        yield 'the same' => [RetentionPeriod::fixed('P5Y'), true];
        yield 'in months' => [RetentionPeriod::fixed('P60M'), true];
        yield 'years and months' => [RetentionPeriod::fixed('P4Y12M'), true];
        yield 'a second more' => [RetentionPeriod::fixed('P5YT1S'), true];
        yield '1827 days, two leap days' => [RetentionPeriod::fixed('P1827D'), true];
        yield 'longer' => [RetentionPeriod::fixed('P7Y'), true];
        yield '1826 days, short when the five years hold two leap days' => [RetentionPeriod::fixed('P1826D'), false];
        yield 'a month short' => [RetentionPeriod::fixed('P59M'), false];
        yield 'a second short' => [RetentionPeriod::fixed('P4Y11M30DT23H59M59S'), false];
        yield 'an hour' => [RetentionPeriod::fixed('PT1H'), false];
        yield 'indefinite' => [RetentionPeriod::indefinite(), true];
        yield 'until an event' => [RetentionPeriod::untilEvent('the end of the contract'), true];
        yield 'not yet fixed' => [RetentionPeriod::notYetFixed('the retention policy'), true];
    }

    #[DataProvider('againstFiveYears')]
    public function testAPeriodLastsAtLeastAnotherWhenItNeverPurgesSooner(RetentionPeriod $period, bool $expected): void
    {
        self::assertSame($expected, $period->lastsAtLeast(RetentionPeriod::fixed('P5Y')));
    }

    public function testAMinimumThatIsNotANumberAsksNothing(): void
    {
        self::assertTrue(RetentionPeriod::fixed('P1D')->lastsAtLeast(RetentionPeriod::indefinite()));
    }
}
