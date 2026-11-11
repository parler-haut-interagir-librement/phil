<?php

declare(strict_types=1);

namespace Phil\OpeningHours;

use Phil\OpeningHours\Exception\InvalidOpeningHours;

/**
 * The ranges of one day, in order and apart; only the last may close the next day. None is a closed day.
 */
final readonly class DayHours
{
    /**
     * @param list<TimeRange> $ranges
     */
    private function __construct(
        public array $ranges,
    ) {
    }

    /**
     * @param list<TimeRange> $ranges in any order
     */
    public static function of(array $ranges): self
    {
        usort($ranges, static fn (TimeRange $a, TimeRange $b): int => $a->opens->minutes <=> $b->opens->minutes);
        $previous = null;

        foreach ($ranges as $range) {
            if ($previous instanceof TimeRange && ($previous->isOvernight() || $range->opens->minutes < $previous->closes->minutes)) {
                throw InvalidOpeningHours::because(\sprintf('The ranges %s and %s of a day overlap.', $previous, $range));
            }

            $previous = $range;
        }

        return new self($ranges);
    }

    public static function closed(): self
    {
        return new self([]);
    }

    public function isClosed(): bool
    {
        return [] === $this->ranges;
    }

    public function equals(self $other): bool
    {
        return \count($this->ranges) === \count($other->ranges)
            && array_all($this->ranges, static fn (TimeRange $range, int $index): bool => $range->equals($other->ranges[$index]));
    }

    /**
     * `09:00-12:00, 13:00-17:00`; empty for a closed day.
     */
    public function toText(): string
    {
        return implode(', ', $this->ranges);
    }
}
