<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BillingPeriod::class)]
final class BillingPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{BillingUnit, int, string, int, string}>
     */
    public static function ends(): iterable
    {
        yield 'a day' => [BillingUnit::Day, 1, '2026-01-31 10:00', 1, '2026-02-01 10:00'];
        yield 'two weeks, twice' => [BillingUnit::Week, 2, '2026-01-01 00:00', 2, '2026-01-29 00:00'];
        yield 'a month from the 15th' => [BillingUnit::Month, 1, '2026-01-15 08:30', 1, '2026-02-15 08:30'];
        yield 'a month from the 31st, in February' => [BillingUnit::Month, 1, '2026-01-31 08:30', 1, '2026-02-28 08:30'];
        yield 'a month from the 31st, back to the 31st' => [BillingUnit::Month, 1, '2026-01-31 08:30', 2, '2026-03-31 08:30'];
        yield 'a month from the 31st, in a leap February' => [BillingUnit::Month, 1, '2028-01-31 00:00', 1, '2028-02-29 00:00'];
        yield 'a month from the 30th, in April' => [BillingUnit::Month, 1, '2026-03-31 00:00', 1, '2026-04-30 00:00'];
        yield 'a quarter across the year' => [BillingUnit::Month, 3, '2026-11-30 00:00', 1, '2027-02-28 00:00'];
        yield 'twelve months' => [BillingUnit::Month, 1, '2026-12-15 00:00', 12, '2027-12-15 00:00'];
        yield 'a year from February 29th' => [BillingUnit::Year, 1, '2028-02-29 12:00', 1, '2029-02-28 12:00'];
        yield 'four years from February 29th' => [BillingUnit::Year, 1, '2028-02-29 12:00', 4, '2032-02-29 12:00'];
        yield 'no period' => [BillingUnit::Month, 1, '2026-05-31 00:00', 0, '2026-05-31 00:00'];
    }

    #[DataProvider('ends')]
    public function testPeriodsAreCountedFromTheAnchor(BillingUnit $unit, int $count, string $anchor, int $periods, string $end): void
    {
        $period = BillingPeriod::of($unit, $count);

        self::assertSame($unit, $period->unit);
        self::assertSame($count, $period->count);
        self::assertSame($end, $period->after(new DateTimeImmutable($anchor.' UTC'), $periods)->format('Y-m-d H:i'));
    }

    public function testTheTimeZoneOfTheAnchorIsKept(): void
    {
        $end = BillingPeriod::of(BillingUnit::Month)->after(new DateTimeImmutable('2026-01-31 23:30', new DateTimeZone('America/Toronto')), 1);

        self::assertSame('2026-02-28 23:30 America/Toronto', $end->format('Y-m-d H:i e'));
    }

    public function testBoundsAreAcceptedAndPeriodsCompared(): void
    {
        self::assertSame(36, BillingPeriod::of(BillingUnit::Day, 36)->count);
        self::assertTrue(BillingPeriod::of(BillingUnit::Month)->equals(BillingPeriod::of(BillingUnit::Month, 1)));
        self::assertFalse(BillingPeriod::of(BillingUnit::Month)->equals(BillingPeriod::of(BillingUnit::Year)));
        self::assertFalse(BillingPeriod::of(BillingUnit::Month)->equals(BillingPeriod::of(BillingUnit::Month, 2)));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidCounts(): iterable
    {
        yield 'zero' => [0];
        yield 'beyond three years of units' => [37];
    }

    #[DataProvider('invalidCounts')]
    public function testACountOutsideItsBoundsIsRefused(int $count): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A billing period counts 1 to 36 units; %d does not.', $count));

        BillingPeriod::of(BillingUnit::Month, $count);
    }
}
