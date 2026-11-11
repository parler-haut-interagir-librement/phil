<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Internal\DateMatcher;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateMatcher::class)]
final class DateMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{int, int, int}, string, int}>
     */
    public static function whole(): iterable
    {
        yield 'day, month, four-digit year, slashes' => ['14/03/1987', [1987, 3, 14], '/', 39 * 365 * 4];
        yield 'year, month, day, dashes' => ['1987-3-14', [1987, 3, 14], '-', 39 * 365 * 4];
        yield 'two-digit year above the pivot' => ['14.03.87', [1987, 3, 14], '.', 39 * 365 * 4];
        yield 'month, day, year' => ['3/14/2001', [2001, 3, 14], '/', 25 * 365 * 4];
        yield 'year, day, month, spaces' => ['2001 14 03', [2001, 3, 14], ' ', 25 * 365 * 4];
        yield 'underscores' => ['1987_03_14', [1987, 3, 14], '_', 39 * 365 * 4];
        yield 'backslashes' => ['1987\\03\\14', [1987, 3, 14], '\\', 39 * 365 * 4];
        yield 'day, month, short year, no separator' => ['140387', [1987, 3, 14], '', 39 * 365];
        yield 'year, month, day, no separator' => ['19870314', [1987, 3, 14], '', 39 * 365];
        yield 'two-digit year at the pivot' => ['010150', [2050, 1, 1], '', 24 * 365];
        yield 'two-digit year above the pivot, no separator' => ['010151', [1951, 1, 1], '', 75 * 365];
        yield 'the nearest year of the readings' => ['01011950', [1950, 1, 1], '', 76 * 365];
        yield 'a recent year costs the least span' => ['01012020', [2020, 1, 1], '', 20 * 365];
        yield 'the last day of a month' => ['31122000', [2000, 12, 31], '', 26 * 365];
        yield 'the last year' => ['01012099', [2099, 1, 1], '', 73 * 365];
        yield 'the first year' => ['01011000', [1000, 1, 1], '', 1026 * 365];
    }

    /**
     * @param array{int, int, int} $date
     */
    #[DataProvider('whole')]
    public function testAWholeDate(string $password, array $date, string $separator, int $guesses): void
    {
        $match = $this->covering($password);

        self::assertSame(Pattern::Date, $match->pattern);
        self::assertSame($date, [$match->year, $match->month, $match->day]);
        self::assertSame($separator, $match->separator);
        self::assertSame($password, $match->token);
        self::assertSame((float) $guesses, $match->guesses);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDates(): iterable
    {
        yield 'day zero' => ['00000000'];
        yield 'month and day thirteen' => ['2001-13-13'];
        yield 'day thirty-two' => ['2001-01-32'];
        yield 'month zero' => ['01/00/2001'];
        yield 'mixed separators' => ['2001-01/01'];
        yield 'a three-digit year' => ['01/01/200'];
        yield 'a one-digit year only' => ['1/1/1'];
        yield 'a year after the last' => ['01012100'];
        yield 'a year before the first' => ['01010999'];
        yield 'a three-digit month' => ['2001-1-011'];
        yield 'nine digits' => ['198703141'];
        yield 'a letter' => ['1987a314'];
    }

    #[DataProvider('notDates')]
    public function testNotAWholeDate(string $password): void
    {
        $count = mb_strlen($password);

        self::assertSame([], array_values(array_filter(
            new DateMatcher(2026)->matches(mb_str_split($password)),
            static fn (PatternMatch $match): bool => 0 === $match->start && $count - 1 === $match->end && Pattern::Date === $match->pattern,
        )));
    }

    public function testYearsFrom1900To2099(): void
    {
        $years = array_values(array_filter(
            new DateMatcher(2026)->matches(mb_str_split('x2024y1899z1900')),
            static fn (PatternMatch $match): bool => Pattern::Year === $match->pattern,
        ));

        self::assertSame(
            [['2024', 1, 4, 2024, 20.0], ['1900', 11, 14, 1900, 126.0]],
            array_map(static fn (PatternMatch $match): array => [$match->token, $match->start, $match->end, $match->year, $match->guesses], $years),
        );
        self::assertSame([Pattern::Date], array_map(
            static fn (PatternMatch $match): Pattern => $match->pattern,
            new DateMatcher(2026)->matches(mb_str_split('2100')),
        ));
    }

    public function testShortPartsAreNotDates(): void
    {
        self::assertSame([], new DateMatcher(2026)->matches(mb_str_split('111')));
        self::assertSame([], new DateMatcher(2026)->matches([]));
    }

    public function testTheReferenceYear(): void
    {
        self::assertSame(20.0, new DateMatcher(1990)->matches(mb_str_split('1990'))[1]->guesses);
        self::assertSame(36.0, new DateMatcher(2026)->matches(mb_str_split('1990'))[1]->guesses);
    }

    public function testTheNearestReadingWhicheverComesFirst(): void
    {
        $date = self::covering('10/11/12');
        self::assertSame([2012, 11, 10], [$date->year, $date->month, $date->day]);

        // The first of two readings at the same distance.
        $match = null;

        foreach (new DateMatcher(2002)->matches(mb_str_split('01/02/03')) as $candidate) {
            if (0 === $candidate->start && 7 === $candidate->end) {
                $match = $candidate;
            }
        }

        self::assertNotNull($match);
        self::assertSame([2001, 2, 3], [$match->year, $match->month, $match->day]);
    }

    public function testAOneDigitLastPart(): void
    {
        $date = self::covering('197471');

        self::assertSame([1974, 7, 1], [$date->year, $date->month, $date->day]);
    }

    public function testOnlyDigitsMakeAYear(): void
    {
        $years = static fn (string $password): array => array_values(array_filter(
            new DateMatcher(2026)->matches(mb_str_split($password)),
            static fn (PatternMatch $match): bool => Pattern::Year === $match->pattern,
        ));

        self::assertSame([], $years('19a4'));
        self::assertSame([], $years('1899'));
        self::assertCount(1, $years('2099'));
    }

    private function covering(string $password): PatternMatch
    {
        $count = mb_strlen($password);

        foreach (new DateMatcher(2026)->matches(mb_str_split($password)) as $match) {
            if (0 === $match->start && $count - 1 === $match->end && Pattern::Date === $match->pattern) {
                return $match;
            }
        }

        self::fail('No date covers '.$password);
    }
}
