<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use Collator;
use DateTimeImmutable;
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;
use Phil\ReportingBundle\Domain\Training\UnitNode;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The training activities of the source totalled by period — the whole period, or its months, quarters or years — and
 * by organisational unit, each unit with the units below it or alone, in the order of the tree.
 *
 * The activities are read once, as the source yields them; only the totals are held, at most 50 000.
 */
final readonly class TrainingLedger
{
    /** The most totals a report holds: a period and a unit for each. */
    public const int MAX_TOTALS = 50_000;

    /**
     * @param positive-int $maxTotals
     */
    public function __construct(
        private TrainingSource $source,
        private ?UnitDirectory $units = null,
        private int $maxTotals = self::MAX_TOTALS,
    ) {
    }

    public function amounts(): CurrencyAmounts
    {
        return new CurrencyAmounts($this->source->currency());
    }

    /**
     * The totals of each period and each unit that has activities, in the periods' order then the tree's: a unit's
     * name, then its identifier, after its parent; the activities without a unit last.
     *
     * @param bool        $withUnitsBelow whether a unit's totals hold those of the units below it, which then have rows of their own too
     * @param string|null $within         a unit whose activities alone are counted, those of the units below it included
     *
     * @return list<TrainingTotals>
     *
     * @throws ReportTooLarge when there would be more than 50 000 totals
     * @throws InvalidFigures when the step cuts the period in more than 500 parts
     */
    public function byUnit(string $report, Period $period, ?TrainingStep $step, bool $withUnitsBelow = true, ?string $within = null): array
    {
        $buckets = $this->buckets($period, $step);
        /** @var array<string, list<UnitNode>> $paths */
        $paths = [];
        /** @var array<int, array<string, array{int, int, int, int}>> $sums */
        $sums = [];
        $count = 0;

        foreach ($this->source->activities($period) as $activity) {
            $bucket = $this->bucketOf($buckets, $activity->on);

            if (null === $bucket) {
                continue;
            }

            $path = null === $activity->unit ? [] : $paths[$activity->unit] ??= $this->path($activity->unit);
            $ids = array_map(static fn (UnitNode $node): string => $node->id, $path);

            foreach ($this->keys($ids, $withUnitsBelow, $within) as [$key, $depth]) {
                if (!isset($sums[$bucket][$key])) {
                    if ($this->maxTotals === $count++) {
                        throw ReportTooLarge::of($report, $this->maxTotals);
                    }

                    $sums[$bucket][$key] = [0, 0, 0, 0];
                    $paths[$key] = \array_slice($path, 0, $depth);
                }

                $sums[$bucket][$key] = $this->add($sums[$bucket][$key], [1, $activity->hundredthsOfHours, $activity->cost, $activity->eligibleCost]);
            }
        }

        return $this->ordered($buckets, $sums, $paths);
    }

    /**
     * The totals an activity of the unit path counts in, each a key and the length of its path: the unit, and those
     * above it up to `$within` when the units below count; `''` for an activity without a unit; none outside `$within`.
     *
     * @param list<string> $ids
     *
     * @return list<array{string, int}>
     */
    private function keys(array $ids, bool $withUnitsBelow, ?string $within): array
    {
        $from = null === $within ? 0 : array_search($within, $ids, true);
        $depth = \count($ids);

        if (false === $from) {
            return [];
        }

        if (0 === $depth) {
            return [['', 0]];
        }

        if (!$withUnitsBelow) {
            return [[$ids[$depth - 1], $depth]];
        }

        $keys = [];

        for ($i = $from; $i < $depth; ++$i) {
            $keys[] = [$ids[$i], $i + 1];
        }

        return $keys;
    }

    /**
     * @param non-empty-list<Period>                               $buckets
     * @param array<int, array<string, array{int, int, int, int}>> $sums
     * @param array<string, list<UnitNode>>                        $paths
     *
     * @return list<TrainingTotals>
     */
    private function ordered(array $buckets, array $sums, array $paths): array
    {
        ksort($sums);
        $collator = new Collator('root');
        $totals = [];

        foreach ($sums as $bucket => $units) {
            $keys = array_keys($units);
            usort($keys, static fn (int|string $a, int|string $b): int => self::compare($collator, $paths[$a] ?? [], $paths[$b] ?? []));

            foreach ($keys as $key) {
                $totals[] = new TrainingTotals($buckets[$bucket], $paths[$key] ?? [], ...$units[$key]);
            }
        }

        return $totals;
    }

    /**
     * The totals of each period, every unit together, a period without activities included.
     *
     * @return list<TrainingTotals>
     *
     * @throws InvalidFigures when the step cuts the period in more than 500 parts
     */
    public function overall(Period $period, ?TrainingStep $step): array
    {
        $buckets = $this->buckets($period, $step);
        $sums = array_fill(0, \count($buckets), [0, 0, 0, 0]);

        foreach ($this->source->activities($period) as $activity) {
            $bucket = $this->bucketOf($buckets, $activity->on);

            if (null !== $bucket) {
                $sums[$bucket] = $this->add($sums[$bucket], [1, $activity->hundredthsOfHours, $activity->cost, $activity->eligibleCost]);
            }
        }

        return array_map(static fn (Period $bucket, array $sum): TrainingTotals => new TrainingTotals($bucket, [], ...$sum), $buckets, $sums);
    }

    /**
     * @return list<UnitNode>
     */
    private function path(string $unit): array
    {
        $path = $this->units?->path($unit) ?? [];

        return $unit === array_last($path)?->id ? $path : [new UnitNode($unit, $unit)];
    }

    /**
     * @return non-empty-list<Period>
     */
    private function buckets(Period $period, ?TrainingStep $step): array
    {
        return $step instanceof TrainingStep ? $period->split($step->interval()) : [$period];
    }

    /**
     * The index of the period the moment falls in, the periods consecutive; null outside them.
     *
     * @param non-empty-list<Period> $buckets
     */
    private function bucketOf(array $buckets, DateTimeImmutable $moment): ?int
    {
        $low = 0;
        $high = \count($buckets) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($buckets[$middle]->start <= $moment) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $buckets[$low]->contains($moment) ? $low : null;
    }

    /**
     * @param array{int, int, int, int} $sum
     * @param array{int, int, int, int} $more
     *
     * @return array{int, int, int, int}
     */
    private function add(array $sum, array $more): array
    {
        foreach ($more as $i => $value) {
            $sum[$i] += $value;
        }

        return $sum;
    }

    /**
     * @param list<UnitNode> $a
     * @param list<UnitNode> $b
     */
    private static function compare(Collator $collator, array $a, array $b): int
    {
        if ([] === $a || [] === $b) {
            return ([] === $a) <=> ([] === $b);
        }

        $length = min(\count($a), \count($b));

        for ($i = 0; $i < $length; ++$i) {
            $order = (int) $collator->compare($a[$i]->name, $b[$i]->name);

            if (0 !== $order) {
                return $order;
            }

            $order = strcmp($a[$i]->id, $b[$i]->id);

            if (0 !== $order) {
                return $order;
            }
        }

        return \count($a) <=> \count($b);
    }
}
