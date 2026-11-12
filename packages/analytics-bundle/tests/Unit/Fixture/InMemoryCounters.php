<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Fixture;

use Override;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;

/**
 * Counters in memory, keyed `site|day|metric|dimension|value`.
 */
final class InMemoryCounters implements CounterStore
{
    /**
     * @var array<string, int>
     */
    public array $counters = [];

    #[Override]
    public function increment(CounterKey $key): void
    {
        $id = implode('|', [$key->site->value, $key->day->value, $key->metric, $key->dimension, $key->value]);
        $this->counters[$id] = ($this->counters[$id] ?? 0) + 1;
    }

    #[Override]
    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
    {
        $counts = [];

        foreach ($this->counters as $id => $n) {
            [$s, $day, $m, $d, $value] = explode('|', $id);

            if ($s === $site->value && $m === $metric && $d === $dimension && $day >= $from->value && $day <= $to->value) {
                $counts[$value] = ($counts[$value] ?? 0) + $n;
            }
        }

        return $counts;
    }

    #[Override]
    public function deleteSite(SiteId $site): void
    {
        foreach (array_keys($this->counters) as $id) {
            if (str_starts_with($id, $site->value.'|')) {
                unset($this->counters[$id]);
            }
        }
    }
}
