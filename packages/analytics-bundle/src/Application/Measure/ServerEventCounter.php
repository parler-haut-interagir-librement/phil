<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Measure;

use Phil\AnalyticsBundle\Application\Contract\CollectionProjection;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Psr\Clock\ClockInterface;

/**
 * What the application's server counts for one of its sites, without a browser: an account created, a ticket sold, a
 * notification delivered. One service counts for every site the projection holds, each against its own declaration.
 *
 * **Under the same rules as the collection endpoint.** The event is a value of the site's closed list
 * `server_events`; each dimension sent is one of the site's declared dimensions with one of its declared values, and
 * anything else is dropped; a site that does not collect counts nothing. Nothing that names a person — an account, an
 * address, a free text — can become a counter, and nothing is kept but the counters of the UTC day.
 */
final readonly class ServerEventCounter
{
    public function __construct(
        private CollectionProjection $projection,
        private CounterStore $store,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Adds one to the event under `server_events`, `event`, and one to each declared dimension sent under
     * `server_events.<event>`.
     *
     * @param array<string, string> $dimensions the value of each declared dimension, such as `['plan' => 'pro']`
     *
     * @return bool whether the event was counted: `false` for a site the projection does not hold, a site that does
     *              not collect, or an event the site does not declare
     */
    public function count(SiteId $site, string $event, array $dimensions = []): bool
    {
        $declared = $this->projection->site($site);

        if (!$declared instanceof CollectionSite || !$declared->status->collects() || !$declared->plan->serverEvents->has($event)) {
            return false;
        }

        $day = UtcDay::of($this->clock->now());
        $this->store->increment(CounterKey::of($declared->site, $day, MeasureNames::SERVER_EVENTS, 'event', $event));

        foreach ($declared->plan->dimensions as $dimension) {
            $value = $dimensions[$dimension->name] ?? null;

            if (null !== $value && $dimension->values->has($value)) {
                $this->store->increment(CounterKey::of($declared->site, $day, MeasureNames::SERVER_EVENTS.'.'.$event, $dimension->name, $value));
            }
        }

        return true;
    }
}
