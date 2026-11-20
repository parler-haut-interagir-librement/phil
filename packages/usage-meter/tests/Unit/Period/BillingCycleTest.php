<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Period;

use DateTimeImmutable;
use DateTimeZone;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Period\BillingCycle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BillingCycle::class)]
final class BillingCycleTest extends TestCase
{
    /**
     * @return iterable<string, array{BillingCycle, string, string, string}>
     */
    public static function periods(): iterable
    {
        $march = new DateTimeImmutable('2026-03-14T10:30:00+00:00');
        $endOfMonth = new DateTimeImmutable('2026-01-31T09:00:00+00:00');

        yield 'the anchor starts the first period' => [BillingCycle::monthly($march), '2026-03-14T10:30:00+00:00', '2026-03-14T10:30:00+00:00', '2026-04-14T10:30:00+00:00'];
        yield 'later in the first period' => [BillingCycle::monthly($march), '2026-04-14T10:29:59+00:00', '2026-03-14T10:30:00+00:00', '2026-04-14T10:30:00+00:00'];
        yield 'on the next boundary' => [BillingCycle::monthly($march), '2026-04-14T10:30:00+00:00', '2026-04-14T10:30:00+00:00', '2026-05-14T10:30:00+00:00'];
        yield 'later in a month, after the day' => [BillingCycle::monthly($march), '2026-07-20T00:00:00+00:00', '2026-07-14T10:30:00+00:00', '2026-08-14T10:30:00+00:00'];
        yield 'earlier in a month, before the day' => [BillingCycle::monthly($march), '2026-07-02T00:00:00+00:00', '2026-06-14T10:30:00+00:00', '2026-07-14T10:30:00+00:00'];
        yield 'across a year' => [BillingCycle::monthly($march), '2027-01-01T00:00:00+00:00', '2026-12-14T10:30:00+00:00', '2027-01-14T10:30:00+00:00'];
        yield 'before the anchor' => [BillingCycle::monthly($march), '2026-02-01T00:00:00+00:00', '2026-01-14T10:30:00+00:00', '2026-02-14T10:30:00+00:00'];
        yield 'long before the anchor' => [new BillingCycle($march, 3), '2025-11-20T00:00:00+00:00', '2025-09-14T10:30:00+00:00', '2025-12-14T10:30:00+00:00'];
        yield 'the 31st in February' => [BillingCycle::monthly($endOfMonth), '2026-03-01T00:00:00+00:00', '2026-02-28T09:00:00+00:00', '2026-03-31T09:00:00+00:00'];
        yield 'the 31st in a leap February' => [BillingCycle::monthly(new DateTimeImmutable('2028-01-31T09:00:00+00:00')), '2028-02-29T09:00:00+00:00', '2028-02-29T09:00:00+00:00', '2028-03-31T09:00:00+00:00'];
        yield 'the 31st in April' => [BillingCycle::monthly($endOfMonth), '2026-04-30T08:59:59+00:00', '2026-03-31T09:00:00+00:00', '2026-04-30T09:00:00+00:00'];
        yield 'quarterly' => [new BillingCycle($march, 3), '2026-08-01T00:00:00+00:00', '2026-06-14T10:30:00+00:00', '2026-09-14T10:30:00+00:00'];
        yield 'quarterly, before the day of the third month' => [new BillingCycle($march, 3), '2026-09-14T10:29:59+00:00', '2026-06-14T10:30:00+00:00', '2026-09-14T10:30:00+00:00'];
        yield 'yearly' => [BillingCycle::yearly($march), '2027-03-13T00:00:00+00:00', '2026-03-14T10:30:00+00:00', '2027-03-14T10:30:00+00:00'];
        yield 'yearly, past the anniversary' => [BillingCycle::yearly($march), '2027-03-15T00:00:00+00:00', '2027-03-14T10:30:00+00:00', '2028-03-14T10:30:00+00:00'];
        yield 'in the anchor zone' => [BillingCycle::monthly(new DateTimeImmutable('2026-01-01T00:00:00', new DateTimeZone('America/Toronto'))), '2026-04-01T03:59:59+00:00', '2026-03-01T00:00:00-05:00', '2026-04-01T00:00:00-04:00'];
    }

    #[DataProvider('periods')]
    public function testPeriodsAreCountedFromTheAnchor(BillingCycle $cycle, string $at, string $start, string $end): void
    {
        $window = $cycle->windowAt(new DateTimeImmutable($at));

        self::assertSame($start, $window->start->format(\DATE_ATOM));
        self::assertSame($end, $window->end->format(\DATE_ATOM));
        self::assertSame($start, $window->counterStart->format(\DATE_ATOM));
        self::assertSame($end, $window->counterEnd->format(\DATE_ATOM));
    }

    public function testTheSubjectAnchorWins(): void
    {
        $cycle = BillingCycle::monthly(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $window = $cycle->windowAt(new DateTimeImmutable('2026-05-10T00:00:00+00:00'), new DateTimeImmutable('2026-02-20T12:00:00+00:00'));

        self::assertSame('2026-04-20T12:00:00+00:00', $window->start->format(\DATE_ATOM));
        self::assertSame('2026-05-20T12:00:00+00:00', $window->end->format(\DATE_ATOM));
    }

    public function testTheAnchorAndLengthAreKept(): void
    {
        $anchor = new DateTimeImmutable('2026-01-01');

        self::assertSame($anchor, BillingCycle::monthly($anchor)->anchor);
        self::assertSame(1, BillingCycle::monthly($anchor)->months);
        self::assertSame(1, new BillingCycle($anchor)->months);
        self::assertSame(12, BillingCycle::yearly($anchor)->months);
        self::assertSame(120, new BillingCycle($anchor, 120)->months);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLengths(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'past ten years' => [121];
    }

    #[DataProvider('invalidLengths')]
    public function testAPeriodLastsOneToHundredTwentyMonths(int $months): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A billing period lasts 1 to 120 months; got %d.', $months));

        new BillingCycle(new DateTimeImmutable('2026-01-01'), $months);
    }
}
