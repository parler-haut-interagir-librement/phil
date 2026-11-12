<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Almanac;
use Phil\Almanac\ComputedDate;
use Phil\Almanac\DateSpan;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Exception\OutOfRange;
use Phil\Almanac\Rule\Offset;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\RuleParser;
use Phil\Almanac\Rule\WithinTheYear;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Almanac::class)]
#[CoversClass(Definition::class)]
#[CoversClass(DateSpan::class)]
#[CoversClass(ComputedDate::class)]
final class AlmanacTest extends TestCase
{
    private DateTimeZone $toronto;

    protected function setUp(): void
    {
        $this->toronto = new DateTimeZone('America/Toronto');
    }

    public function testADefinitionsSpans(): void
    {
        $oktoberfest = new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03'));
        $week = new Definition('engineers', 'Engineers', RuleParser::parse('monday on or before 02-22'), days: 7);
        $moon = new Definition('moon', 'Full moon', RuleParser::parse('full moon'), allDay: false);
        $moonDay = new Definition('moon-day', 'Full moon', RuleParser::parse('full moon'));
        $friday = new Definition('friday-13', 'Friday 13', RuleParser::parse('friday 13'));

        self::assertSame([['2026-09-19 00:00', '2026-10-04 00:00', true, 16]], $this->spans($oktoberfest->spans(2026, $this->toronto)));
        self::assertSame([['2026-02-16 00:00', '2026-02-22 00:00', true, 7]], $this->spans($week->spans(2026, $this->toronto)));
        self::assertSame(['2026-01-03 05:02', '2026-01-03 05:02', false, 1], $this->spans($moon->spans(2026, $this->toronto))[0]);
        self::assertSame(['2026-01-03 00:00', '2026-01-03 00:00', true, 1], $this->spans($moonDay->spans(2026, $this->toronto))[0]);
        self::assertCount(3, $friday->spans(2026, $this->toronto));
        self::assertSame([], new Definition('leap', 'Leap day', RuleParser::parse('02-29'))->spans(2026, $this->toronto));
    }

    /**
     * An end on the start's own day, in the next year, or never found.
     */
    public function testTheEndOfAnUntil(): void
    {
        $sameDay = new Definition('a', 'A', RuleParser::parse('12-25'), RuleParser::parse('12-25'));
        $nextYear = new Definition('b', 'B', RuleParser::parse('12-30'), RuleParser::parse('01-02'));
        $never = new Definition('c', 'C', RuleParser::parse('12-30'), RuleParser::parse('02-29'));
        $instant = new Definition('d', 'D', RuleParser::parse('12-01'), RuleParser::parse('december solstice'));

        self::assertSame([['2026-12-25 00:00', '2026-12-25 00:00', true, 1]], $this->spans($sameDay->spans(2026, $this->toronto)));
        self::assertSame([['2026-12-30 00:00', '2027-01-02 00:00', true, 4]], $this->spans($nextYear->spans(2026, $this->toronto)));
        self::assertSame([['2026-12-30 00:00', '2026-12-30 00:00', true, 1]], $this->spans($never->spans(2026, $this->toronto)));
        self::assertSame([['2027-12-30 00:00', '2028-02-29 00:00', true, 62]], $this->spans($never->spans(2027, $this->toronto)));
        self::assertSame([['2026-12-01 00:00', '2026-12-21 00:00', true, 21]], $this->spans($instant->spans(2026, $this->toronto)));
    }

    /**
     * @param Closure(): Definition $definition
     */
    #[DataProvider('invalidDefinitions')]
    public function testADefinitionIsRefused(Closure $definition, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $definition();
    }

    /**
     * @return iterable<string, array{Closure(): Definition, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        $christmas = RuleParser::parse('12-25');
        $moon = RuleParser::parse('full moon');

        yield 'a key in capitals' => [static fn (): Definition => new Definition('Noel', 'T', $christmas), '"Noel" is not a key: a lowercase letter or digit, then up to 99 of them, ".", "-" or "_".'];
        yield 'an empty key' => [static fn (): Definition => new Definition('', 'T', $christmas), '"" is not a key'];
        yield 'a key that starts with a dash' => [static fn (): Definition => new Definition('-noel', 'T', $christmas), '"-noel" is not a key'];
        yield 'a key with a space' => [static fn (): Definition => new Definition('noel day', 'T', $christmas), '"noel day" is not a key'];
        yield 'a key and a new line' => [static fn (): Definition => new Definition("noel\n", 'T', $christmas), 'is not a key'];
        yield 'a key of 101 characters' => [static fn (): Definition => new Definition(str_repeat('a', 101), 'T', $christmas), 'is not a key'];
        yield 'a blank title' => [static fn (): Definition => new Definition('k', ' ', $christmas), 'The date "k" has no title.'];
        yield 'no day' => [static fn (): Definition => new Definition('k', 'T', $christmas, days: 0), 'The date "k" lasts 1 to 366 days, or until another rule; not both.'];
        yield 'too many days' => [static fn (): Definition => new Definition('k', 'T', $christmas, days: 367), 'lasts 1 to 366 days'];
        yield 'days and until' => [static fn (): Definition => new Definition('k', 'T', $christmas, RuleParser::parse('12-26'), 2), 'not both.'];
        yield 'a day at a time' => [static fn (): Definition => new Definition('k', 'T', $christmas, allDay: false), 'Only an instant of the sky, a phase, a season or an eclipse, can be shown at its time; "k" is 12-25.'];
        yield 'an instant until' => [static fn (): Definition => new Definition('k', 'T', $moon, RuleParser::parse('new moon'), allDay: false), 'can be shown at its time'];
        yield 'an instant for days' => [static fn (): Definition => new Definition('k', 'T', $moon, days: 3, allDay: false), 'can be shown at its time; "k" is full moon.'];
    }

    public function testTheLimitsOfADefinition(): void
    {
        $definition = new Definition(str_repeat('a', 100), 'T', RuleParser::parse('12-25'), days: 366, tags: ['x']);

        self::assertSame([str_repeat('a', 100), 'T', '12-25', null, 366, true, ['x']], [$definition->key, $definition->title, (string) $definition->rule, $definition->until, $definition->days, $definition->allDay, $definition->tags]);
        self::assertSame('9.a-b_c', new Definition('9.a-b_c', 'T', RuleParser::parse('12-25'))->key);
        self::assertSame([], new Definition('k', 'T', RuleParser::parse('12-25'))->tags);
    }

    public function testThePeriodOfAnAlmanac(): void
    {
        $almanac = new Almanac([
            new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03'), tags: ['fete']),
            new Definition('moon', 'Pleine lune', RuleParser::parse('full moon'), allDay: false, tags: ['lune']),
            new Definition('nye', 'Saint-Sylvestre', RuleParser::parse('12-31'), days: 2, tags: ['fete', 'an']),
            new Definition('noel', 'Noël', RuleParser::parse('12-25')),
            new Definition('a-noel', 'A Noël', RuleParser::parse('12-25')),
        ], $this->toronto);

        self::assertSame(
            ['oktoberfest 2026-09-19', 'moon 2026-09-26', 'moon 2026-10-26', 'moon 2026-11-24', 'moon 2026-12-23', 'a-noel 2026-12-25', 'noel 2026-12-25', 'nye 2026-12-31'],
            $this->keys($almanac->between(new DateTimeImmutable('2026-09-01', $this->toronto), new DateTimeImmutable('2027-01-01', $this->toronto))),
        );
        self::assertSame(['nye 2026-12-31'], $this->keys($almanac->between(new DateTimeImmutable('2027-01-01', $this->toronto), new DateTimeImmutable('2027-01-02', $this->toronto))), 'a span that started the year before');
        self::assertSame([], $this->keys($almanac->between(new DateTimeImmutable('2027-01-02', $this->toronto), new DateTimeImmutable('2027-01-03', $this->toronto))));
        self::assertSame(['oktoberfest 2026-09-19', 'nye 2026-12-31'], $this->keys($almanac->between(new DateTimeImmutable('2026-09-01', $this->toronto), new DateTimeImmutable('2027-01-01', $this->toronto), ['fete'])));
        self::assertSame(['nye 2026-12-31'], $this->keys($almanac->between(new DateTimeImmutable('2026-09-01', $this->toronto), new DateTimeImmutable('2027-01-01', $this->toronto), ['an', 'none'])));
        self::assertSame([], $almanac->between(new DateTimeImmutable('2026-09-01', $this->toronto), new DateTimeImmutable('2027-01-01', $this->toronto), []));
        self::assertSame(['oktoberfest', 'moon', 'nye', 'noel', 'a-noel'], array_keys($almanac->definitions()));
        self::assertSame(['moon 2026-09-26'], $this->keys($almanac->between(new DateTimeImmutable('2026-09-26 12:48:00 America/Toronto'), new DateTimeImmutable('2026-09-26 12:49 America/Toronto'), ['lune'])));
    }

    /**
     * The bounds are instants; the dates are days in the almanac's time zone.
     */
    public function testThePeriodIsReadInTheZone(): void
    {
        $almanac = new Almanac([new Definition('new-year', 'New Year', RuleParser::parse('01-01'))], $this->toronto);

        self::assertSame(['new-year 2027-01-01'], $this->keys($almanac->between(new DateTimeImmutable('2027-01-01 04:00 UTC'), new DateTimeImmutable('2027-01-01 06:00 UTC'))));
        self::assertSame([], $this->keys($almanac->between(new DateTimeImmutable('2027-01-01 03:00 UTC'), new DateTimeImmutable('2027-01-01 04:00 UTC'))), 'still December 31 in Toronto');
    }

    /**
     * A date an offset moves into the period from a year around it, and the years around a rule's range.
     */
    public function testTheYearsAroundThePeriod(): void
    {
        $almanac = new Almanac([
            new Definition('noel', 'Noël', RuleParser::parse('01-06 - 12 days')),
            new Definition('late', 'Late', RuleParser::parse('12-31 + 366 days')),
        ], $this->toronto);

        self::assertSame(['noel 2026-12-25'], $this->keys($almanac->between(new DateTimeImmutable('2026-12-01', $this->toronto), new DateTimeImmutable('2026-12-31', $this->toronto))), 'moved back from the year after');
        self::assertSame(['late 2028-01-01'], $this->keys($almanac->between(new DateTimeImmutable('2028-01-01', $this->toronto), new DateTimeImmutable('2028-01-03', $this->toronto))), 'moved on from two years before');

        $easter = new Almanac([new Definition('easter', 'Pâques', RuleParser::parse('easter'))], $this->toronto);
        self::assertSame(['easter 1583-04-10'], $this->keys($easter->between(new DateTimeImmutable('1583-01-01', $this->toronto), new DateTimeImmutable('1584-01-01', $this->toronto))), 'the years before the range are left out');
        self::assertSame(['easter 4099-04-19'], $this->keys($easter->between(new DateTimeImmutable('4099-01-01', $this->toronto), new DateTimeImmutable('4099-12-01', $this->toronto))), 'and the year after');

        $years = new class implements Rule {
            /** @var list<int> */
            public array $asked = [];

            public function in(int $year, DateTimeZone $zone): array
            {
                $this->asked[] = $year;

                return [];
            }

            public function isInstant(): bool
            {
                return false;
            }

            public function __toString(): string
            {
                return '01-01';
            }
        };
        new Almanac([new Definition('years', 'Years', $years)], $this->toronto)->between(new DateTimeImmutable('2026-03-01', $this->toronto), new DateTimeImmutable('2027-03-01', $this->toronto));
        self::assertSame([2024, 2025, 2026, 2027, 2028], $years->asked, 'two years before the period, its years, one after');

        try {
            $easter->between(new DateTimeImmutable('4099-06-01', $this->toronto), new DateTimeImmutable('4100-06-01', $this->toronto));
            self::fail('A last year of the period out of range.');
        } catch (OutOfRange $outOfRange) {
            self::assertStringContainsString('4100', $outOfRange->getMessage());
        }

        $this->expectException(OutOfRange::class);
        $easter->between(new DateTimeImmutable('1582-06-01', $this->toronto), new DateTimeImmutable('1583-06-01', $this->toronto));
    }

    /**
     * A rule whose dates stay within a week of their year is read over the years its dates, moved and lasting, can
     * touch the period from: a week before January 1 at the earliest, a week after December 31 at the latest.
     */
    public function testAWithinTheYearRuleReadsOnlyTheYearsItNeeds(): void
    {
        $recorder = static fn (): object => new class implements WithinTheYear {
            /** @var list<int> */
            public array $asked = [];

            public function in(int $year, DateTimeZone $zone): array
            {
                $this->asked[] = $year;

                return [];
            }

            public function isInstant(): bool
            {
                return false;
            }

            public function __toString(): string
            {
                return '01-01';
            }
        };
        $asked = function (?int $offset, int $days, string $from, string $until, ?Rule $untilRule = null) use ($recorder): array {
            $rule = $recorder();
            new Almanac([new Definition('x', 'X', null === $offset ? $rule : new Offset($rule, $offset), $untilRule, $days)], $this->toronto)
                ->between(new DateTimeImmutable($from, $this->toronto), new DateTimeImmutable($until, $this->toronto));

            return $rule->asked;
        };

        self::assertSame([2025, 2026, 2027], $asked(null, 1, '2026-01-07', '2026-12-25'), 'a week out of the year either way');
        self::assertSame([2026], $asked(null, 1, '2026-01-08', '2026-12-24 12:00'));
        self::assertSame([2026], $asked(null, 1, '2026-11-01', '2026-12-01'), 'a month reads its year');
        self::assertSame([2025, 2026], $asked(10, 1, '2026-01-17', '2026-12-24 12:00'), 'moved later: from further back');
        self::assertSame([2026], $asked(10, 1, '2026-01-18', '2026-12-24 12:00'));
        self::assertSame([2026], $asked(-10, 1, '2025-12-29', '2026-12-14 12:00'), 'moved earlier: from further on');
        self::assertSame([2025, 2026, 2027], $asked(-10, 1, '2025-12-28 12:00', '2026-12-15'));
        self::assertSame([2025, 2026], $asked(null, 5, '2026-01-11', '2026-06-01'), 'lasting into the period');
        self::assertSame([2026], $asked(null, 5, '2026-01-12', '2026-06-01'));
        self::assertSame([2024, 2025, 2026], $asked(null, 1, '2026-03-01', '2026-04-01', RuleParser::parse('12-31')), 'until another rule: the two years before');
        self::assertSame([2024, 2025, 2026, 2027], $asked(null, 1, '2026-03-01', '2026-04-01', new class implements Rule {
            public function in(int $year, DateTimeZone $zone): array
            {
                return [];
            }

            public function isInstant(): bool
            {
                return false;
            }

            public function __toString(): string
            {
                return '12-31';
            }
        }), 'until a rule that may fall anywhere: the wider years');
    }

    public function testAKeyNamesOneDate(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('The key "noel" names two dates.');

        new Almanac([new Definition('noel', 'A', RuleParser::parse('12-25')), new Definition('noel', 'B', RuleParser::parse('12-24'))], $this->toronto);
    }

    public function testASpanOverlapsAPeriod(): void
    {
        $day = new DateSpan(new DateTimeImmutable('2026-12-25 00:00 UTC'), new DateTimeImmutable('2026-12-26 00:00 UTC'), true);
        $instant = new DateSpan(new DateTimeImmutable('2026-12-25 10:00 UTC'), new DateTimeImmutable('2026-12-25 10:00 UTC'), false);

        self::assertSame([true, true, false, false, true], [
            $day->overlaps(new DateTimeImmutable('2026-12-26 23:59 UTC'), new DateTimeImmutable('2026-12-28 UTC')),
            $day->overlaps(new DateTimeImmutable('2026-12-20 UTC'), new DateTimeImmutable('2026-12-25 00:01 UTC')),
            $day->overlaps(new DateTimeImmutable('2026-12-27 UTC'), new DateTimeImmutable('2026-12-28 UTC')),
            $day->overlaps(new DateTimeImmutable('2026-12-20 UTC'), new DateTimeImmutable('2026-12-25 UTC')),
            $day->overlaps(new DateTimeImmutable('2026-12-25 UTC'), new DateTimeImmutable('2026-12-25 00:00:01 UTC')),
        ]);
        self::assertSame([true, false, false], [
            $instant->overlaps(new DateTimeImmutable('2026-12-25 10:00 UTC'), new DateTimeImmutable('2026-12-25 10:01 UTC')),
            $instant->overlaps(new DateTimeImmutable('2026-12-25 09:00 UTC'), new DateTimeImmutable('2026-12-25 10:00 UTC')),
            $instant->overlaps(new DateTimeImmutable('2026-12-25 10:00:01 UTC'), new DateTimeImmutable('2026-12-26 UTC')),
        ]);
        self::assertSame([2, 1], [$day->days(), $instant->days()]);
        self::assertSame(1, new DateSpan(new DateTimeImmutable('2026-12-25 00:00 UTC'), new DateTimeImmutable('2026-12-25 00:00 UTC'), true)->days());
    }

    /**
     * @param list<DateSpan> $spans
     *
     * @return list<array{string, string, bool, int}>
     */
    private function spans(array $spans): array
    {
        return array_map(static fn (DateSpan $span): array => [$span->start->format('Y-m-d H:i'), $span->end->format('Y-m-d H:i'), $span->allDay, $span->days()], $spans);
    }

    /**
     * @param list<ComputedDate> $dates
     *
     * @return list<string>
     */
    private function keys(array $dates): array
    {
        return array_map(static fn (ComputedDate $date): string => $date->definition->key.' '.$date->span->start->format('Y-m-d'), $dates);
    }
}
