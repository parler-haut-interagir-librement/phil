<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use DateTimeImmutable;
use Phil\VirtualClassroom\Attendance\AttendanceSheet;
use Phil\VirtualClassroom\Attendance\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttendanceSheet::class)]
final class AttendanceSheetTest extends TestCase
{
    private const int START = 1_700_000_000;

    /**
     * @return iterable<string, array{list<array{int, ?int}>, int, int}>
     */
    public static function sheets(): iterable
    {
        yield 'no stay' => [[], 0, 0];
        yield 'the whole session' => [[[0, 3600]], 3600, 100];
        yield 'a stay still open ends with the session' => [[[1800, null]], 1800, 50];
        yield 'a stay before the start is cut' => [[[-600, 900]], 900, 25];
        yield 'a stay after the end is cut' => [[[2700, 4000]], 900, 25];
        yield 'a stay wholly before' => [[[-600, -1]], 0, 0];
        yield 'a stay wholly after' => [[[3600, 4000]], 0, 0];
        yield 'a stay of no length' => [[[100, 100]], 0, 0];
        yield 'a stay that ends before it starts' => [[[200, 100]], 0, 0];
        yield 'two devices at once count once' => [[[0, 1800], [600, 2400]], 2400, 66];
        yield 'one stay inside another' => [[[0, 3000], [600, 1200]], 3000, 83];
        yield 'the stays out of order' => [[[2000, 2600], [0, 600]], 1200, 33];
        yield 'two stays that touch' => [[[0, 1200], [1200, 1800]], 1800, 50];
        yield 'three stays, the second inside the first, the third after' => [[[0, 1000], [200, 500], [900, 1500]], 1500, 41];
        yield 'a stay inside another, listed first' => [[[100, 200], [0, 1000]], 1000, 27];
        yield 'just under a quarter' => [[[0, 899]], 899, 24];
    }

    /**
     * @param list<array{int, ?int}> $stays
     */
    #[DataProvider('sheets')]
    public function testTheSecondsAndTheShareOfASession(array $stays, int $seconds, int $percent): void
    {
        $intervals = array_map(
            static fn (array $stay): Interval => new Interval(self::at($stay[0]), null === $stay[1] ? null : self::at($stay[1])),
            $stays,
        );

        self::assertSame($seconds, AttendanceSheet::seconds($intervals, self::at(0), self::at(3600)));
        self::assertSame($percent, AttendanceSheet::percent($intervals, self::at(0), self::at(3600)));
    }

    public function testASessionOfNoLengthIsNotAttended(): void
    {
        $intervals = [new Interval(self::at(0), self::at(10))];

        self::assertSame(0, AttendanceSheet::percent($intervals, self::at(10), self::at(10)));
        self::assertSame(0, AttendanceSheet::percent($intervals, self::at(10), self::at(5)));
        self::assertSame(0, AttendanceSheet::seconds($intervals, self::at(10), self::at(5)));
    }

    public function testTheShareIsAWholePercentRoundedDown(): void
    {
        $intervals = [new Interval(self::at(0), self::at(2))];

        self::assertSame(66, AttendanceSheet::percent($intervals, self::at(0), self::at(3)));
        self::assertSame(100, AttendanceSheet::percent([new Interval(self::at(0))], self::at(0), self::at(3)));
    }

    private static function at(int $offset): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.(self::START + $offset));
    }
}
