<?php

declare(strict_types=1);

namespace Phil\Almanac;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Exception\OutOfRange;
use Phil\Almanac\Rule\Days;
use Phil\Almanac\Rule\Offset;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\WithinTheYear;

/**
 * Definitions and a time zone: the dates of a period, computed when asked, never stored.
 */
final readonly class Almanac
{
    /** How far out of its year a date of a `WithinTheYear` rule falls at most, in days. */
    private const int WEEK = 7;

    /** @var array<string, Definition> */
    private array $definitions;

    /**
     * @param iterable<Definition> $definitions
     */
    public function __construct(
        iterable $definitions,
        private DateTimeZone $zone,
    ) {
        $byKey = [];

        foreach ($definitions as $definition) {
            if (isset($byKey[$definition->key])) {
                throw InvalidRule::because(\sprintf('The key "%s" names two dates.', $definition->key));
            }

            $byKey[$definition->key] = $definition;
        }

        $this->definitions = $byKey;
    }

    /**
     * @return array<string, Definition> by key
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * Every date that touches a period, its start included and its end not, by start, then title. The bounds are
     * instants: `2026-09-01` read in another time zone than the almanac's is another moment.
     *
     * The years around the period are read too, for a date moved there by an offset or lasting into it; one the rule
     * cannot compute there — Easter before 1583 — is left out. A year of the period itself out of the rule's range is
     * an `OutOfRange`. A `WithinTheYear` rule is read only over the years its dates, moved and lasting, can reach the
     * period from: a month of a fixed date reads one year, not four.
     *
     * @param list<string>|null $tags only the dates with one of these tags
     *
     * @return list<ComputedDate>
     */
    public function between(DateTimeInterface $from, DateTimeInterface $until, ?array $tags = null): array
    {
        $found = [];
        $firstOfPeriod = Days::year($from, $this->zone);
        $lastOfPeriod = Days::year($until, $this->zone);

        foreach ($this->definitions as $definition) {
            if (null !== $tags && [] === array_intersect($tags, $definition->tags)) {
                continue;
            }

            [$first, $last] = $this->years($definition, $from, $until, $firstOfPeriod, $lastOfPeriod);

            for ($year = $first; $year <= $last; ++$year) {
                foreach ($this->spans($definition, $year, $year < $firstOfPeriod || $year > $lastOfPeriod) as $span) {
                    if ($span->overlaps($from, $until)) {
                        $found[] = new ComputedDate($definition, $span);
                    }
                }
            }
        }

        usort($found, static fn (ComputedDate $a, ComputedDate $b): int => [$a->span->start, $a->definition->title] <=> [$b->span->start, $b->definition->title]);

        return $found;
    }

    /**
     * The years of a definition's dates that can touch a period. A span lasts a year at most and an offset moves a
     * date by a year at most: a date that touches the period is a rule's of the two years before its first, of its
     * years, or of the year after its last. A `WithinTheYear` rule's dates fall at most a week out of their year, then
     * move by their offset and last their days: the years are those such dates can touch the period from — a rule that
     * lasts until another keeps the two years before.
     *
     * @return array{int, int}
     */
    private function years(Definition $definition, DateTimeInterface $from, DateTimeInterface $until, int $firstOfPeriod, int $lastOfPeriod): array
    {
        $rule = $definition->rule;
        $offset = $rule instanceof Offset ? $rule->days : 0;

        if (!$this->withinTheYear($rule) || ($definition->until instanceof Rule && !$this->withinTheYear($definition->until))) {
            return [$firstOfPeriod - 2, $lastOfPeriod + 1];
        }

        $moved = fn (DateTimeInterface $moment, int $days): int => Days::year(DateTimeImmutable::createFromInterface($moment)->modify(\sprintf('%+d days', $days)), $this->zone);

        // The latest a date of a year ends is a week after its December 31, moved and lasting; the earliest it starts,
        // a week before its January 1, moved.
        return [
            $definition->until instanceof Rule ? $firstOfPeriod - 2 : $moved($from, -(self::WEEK + $offset + $definition->days - 1)),
            $moved($until, self::WEEK - $offset),
        ];
    }

    private function withinTheYear(Rule $rule): bool
    {
        return ($rule instanceof Offset ? $rule->rule : $rule) instanceof WithinTheYear;
    }

    /**
     * @return list<DateSpan>
     *
     * @throws OutOfRange for a year of the period
     */
    private function spans(Definition $definition, int $year, bool $around): array
    {
        try {
            return $definition->spans($year, $this->zone);
        } catch (OutOfRange $outOfRange) {
            return $around ? [] : throw $outOfRange;
        }
    }
}
