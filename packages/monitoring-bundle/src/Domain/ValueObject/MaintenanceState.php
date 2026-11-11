<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Exception\InvalidValue;

/**
 * The site under maintenance: since when, and in how many seconds visitors are told to come back — the `Retry-After`
 * of the answer they get.
 */
final readonly class MaintenanceState
{
    public function __construct(
        public DateTimeImmutable $since,
        public int $retryAfterSeconds,
    ) {
        if ($retryAfterSeconds < 0) {
            throw InvalidValue::because(\sprintf('Visitors come back in 0 seconds or more, not %d.', $retryAfterSeconds));
        }
    }
}
