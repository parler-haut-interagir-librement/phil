<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Collection;

/**
 * Whether a site is collected. Only an active site is: a suspended site is refused until it is reinstated, and a
 * retired site is refused for good and has its counters deleted by the application.
 */
enum SiteStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Retired = 'retired';

    public function collects(): bool
    {
        return self::Active === $this;
    }
}
