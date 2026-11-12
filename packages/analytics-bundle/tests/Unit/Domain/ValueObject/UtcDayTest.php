<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UtcDay::class)]
final class UtcDayTest extends TestCase
{
    /**
     * 23:30 in Montreal on 31 December is already 1 January in UTC: the day is the UTC day, whatever the zone.
     */
    public function testTheDayOfAnInstantIsItsUtcDay(): void
    {
        $instant = new DateTimeImmutable('2026-12-31 23:30:00', new DateTimeZone('America/Toronto'));

        self::assertSame('2027-01-01', UtcDay::of($instant)->value);
    }

    public function testAStoredDayIsReadBack(): void
    {
        self::assertSame('2028-02-29', UtcDay::fromString('2028-02-29')->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notADay(): iterable
    {
        yield 'not a leap year' => ['2027-02-29'];
        yield 'month 13' => ['2026-13-01'];
        yield 'with a time' => ['2026-10-31T00:00:00'];
        yield 'short form' => ['2026-1-1'];
        yield 'text before' => ['x2026-10-31'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["2026-10-31\n"];
    }

    #[DataProvider('notADay')]
    public function testWhatIsNotACalendarDayIsRefused(string $value): void
    {
        $this->expectException(InvalidValue::class);

        UtcDay::fromString($value);
    }

    public function testADayStartsAtMidnightUtc(): void
    {
        self::assertSame('2026-03-08T00:00:00+00:00', UtcDay::fromString('2026-03-08')->start()->format(\DATE_ATOM));
    }

    /**
     * The day a local clock moves forward is still one UTC day long, and the next day follows it.
     */
    public function testTheNextDayCrossesAMonthAndAClockChange(): void
    {
        self::assertSame('2026-03-09', UtcDay::fromString('2026-03-08')->next()->value);
        self::assertSame('2027-01-01', UtcDay::fromString('2026-12-31')->next()->value);
    }

    public function testDaysCompare(): void
    {
        $first = UtcDay::fromString('2026-10-30');
        $second = UtcDay::fromString('2026-10-31');

        self::assertTrue($first->isBefore($second));
        self::assertFalse($second->isBefore($first));
        self::assertFalse($first->isBefore(UtcDay::fromString('2026-10-30')));
        self::assertTrue($first->equals(UtcDay::fromString('2026-10-30')));
    }
}
