<?php

declare(strict_types=1);

namespace Phil\Reservation;

/**
 * How many units are held at each moment, laid out once from the bookings' spans so that the peak of any span is read
 * by a binary search and a walk over the changes it holds.
 */
final readonly class LoadProfile
{
    /**
     * @param list<int> $points the moments the load changes, in order
     * @param list<int> $loads  the load from each moment to the next one
     */
    private function __construct(
        private array $points,
        private array $loads,
    ) {
    }

    /**
     * @param iterable<array{int, int, int}> $spans each a start, an end excluded, as Unix timestamps, and the units it holds
     */
    public static function of(iterable $spans): self
    {
        $deltas = [];

        foreach ($spans as [$from, $until, $quantity]) {
            $deltas[$from] = ($deltas[$from] ?? 0) + $quantity;
            $deltas[$until] = ($deltas[$until] ?? 0) - $quantity;
        }

        ksort($deltas);
        $load = 0;
        $loads = [];

        foreach ($deltas as $delta) {
            $load += $delta;
            $loads[] = $load;
        }

        return new self(array_keys($deltas), $loads);
    }

    /**
     * The most units held at once in a span, its end excluded.
     */
    public function peak(int $from, int $until): int
    {
        $low = 0;
        $high = \count($this->points);

        while ($low < $high) {
            $middle = ($low + $high) >> 1;

            if ($this->points[$middle] <= $from) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        $peak = $low > 0 ? $this->loads[$low - 1] : 0;

        for ($index = $low, $count = \count($this->points); $index < $count && $this->points[$index] < $until; ++$index) {
            $peak = max($peak, $this->loads[$index]);
        }

        return $peak;
    }
}
