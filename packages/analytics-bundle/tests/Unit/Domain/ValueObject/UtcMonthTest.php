<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UtcMonth::class)]
final class UtcMonthTest extends TestCase
{
    public function testTheMonthOfADay(): void
    {
        $month = UtcMonth::of(UtcDay::fromString('2026-03-31'));

        self::assertSame(2026, $month->year);
        self::assertSame(3, $month->month);
        self::assertSame('2026-03', $month->toString());
        self::assertSame('2026-03', (string) $month);
        self::assertSame('2026-03-01', $month->firstDay()->toString());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function earlierMonths(): iterable
    {
        yield 'same year' => ['2026-11', 1, '2026-10'];
        yield 'into December of the year before' => ['2026-01', 1, '2025-12'];
        yield 'thirteen months' => ['2026-11', 13, '2025-10'];
        yield 'none' => ['2026-12', 0, '2026-12'];
        yield 'exactly a year' => ['2026-12', 12, '2025-12'];
    }

    #[DataProvider('earlierMonths')]
    public function testMinus(string $month, int $months, string $expected): void
    {
        self::assertSame($expected, UtcMonth::fromString($month)->minus($months)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAMonth(): iterable
    {
        yield 'month zero' => ['2026-00'];
        yield 'month thirteen' => ['2026-13'];
        yield 'a day' => ['2026-01-01'];
        yield 'a trailing newline' => ["2026-01\n"];
        yield 'text before' => ['x2026-01'];
    }

    #[DataProvider('notAMonth')]
    public function testWhatIsNotAMonthIsRefused(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not a month in YYYY-MM form.');

        UtcMonth::fromString($value);
    }
}
