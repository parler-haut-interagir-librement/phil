<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Store;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The shape a maintenance state is stored in, `{"since": "<ATOM>", "retry_after": <seconds>}`, shared by the stores.
 */
final class MaintenanceRecord
{
    private function __construct()
    {
    }

    /**
     * @return array{since: string, retry_after: int}
     */
    public static function encode(MaintenanceState $state): array
    {
        return ['since' => $state->since->format(DateTimeInterface::ATOM), 'retry_after' => $state->retryAfterSeconds];
    }

    /**
     * The state of a record; `$fallback` when the record is in another shape, since a record that is there means
     * the site was put into maintenance.
     */
    public static function decode(mixed $record, MaintenanceState $fallback): MaintenanceState
    {
        $since = \is_array($record) && \is_string($record['since'] ?? null) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $record['since']) : false;
        $retryAfter = \is_array($record) ? ($record['retry_after'] ?? null) : null;

        if (false === $since || !\is_int($retryAfter) || $retryAfter < 0) {
            return $fallback;
        }

        return new MaintenanceState($since, $retryAfter);
    }
}
