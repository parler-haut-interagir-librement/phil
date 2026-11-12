<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * The rate limiter that bounds collection, per site and per keyed client digest.
 *
 * **It fails closed.** When its backend cannot answer, `consume()` returns `false` (or throws): a page view lost
 * is better than a counter without a bound. Its state holds the digest and never the address, with a time to
 * live no longer than the budget's window, in a store that does not persist it.
 *
 * The budget is the application's to choose: calibrate it on a group behind a single address (a conference
 * Wi-Fi, a carrier-grade NAT), not on a single visitor.
 */
interface CollectionRateLimiter
{
    /**
     * Whether one more page view from this digest is within the site's budget.
     */
    public function consume(SiteId $site, ClientDigest $digest): bool;
}
