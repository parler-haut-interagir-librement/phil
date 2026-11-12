<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;

/**
 * Where the counters live.
 *
 * **Counters only.** The adapter stores a number per {@see CounterKey}, and nothing that produced it: no
 * instant finer than the day, no address, no user agent, no referrer URL. An increment is a single atomic
 * upsert (an `INSERT ... ON CONFLICT DO UPDATE SET n = n + 1`, or the equivalent), so that concurrent page views
 * never lose a count and a store that can only increment needs no other privilege.
 */
interface CounterStore
{
    public function increment(CounterKey $key): void;

    /**
     * The stored counters of one metric and one dimension over an inclusive range of days, summed per dimension
     * value. The small-number rule is applied by the caller, on read, over what this returns.
     *
     * @return array<string, int>
     */
    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array;

    /**
     * Deletes every counter of a site, when the site is retired.
     */
    public function deleteSite(SiteId $site): void;
}
