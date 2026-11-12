<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;

/**
 * The host an outbound link leads to, for a counter: any host, small numbers being hidden on read.
 */
final readonly class OutboundHost
{
    /**
     * The counter value of a host a page reported, or `null`: not a host name, or one of the site's own.
     *
     * @param iterable<Origin> $ownOrigins
     */
    public static function valueOf(string $host, iterable $ownOrigins): ?string
    {
        $host = strtolower($host);

        if (\strlen($host) > CounterKey::MAX_VALUE_LENGTH
            || 1 !== preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/D', $host)) {
            return null;
        }

        foreach ($ownOrigins as $origin) {
            if ($origin->host === $host) {
                return null;
            }
        }

        return $host;
    }
}
