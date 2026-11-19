<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application\Contract;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;

/**
 * What the administration pages and the purge read of the store, beside the writes of the tracker.
 */
interface ErrorGroupReader
{
    /**
     * The groups seen last first, of one status or of all.
     *
     * @return list<ErrorGroup>
     */
    public function groups(?GroupStatus $status, int $limit, int $offset = 0): array;

    public function countGroups(?GroupStatus $status): int;

    /**
     * The events kept for a group, the latest first.
     *
     * @return list<ErrorEvent>
     */
    public function events(string $fingerprint, int $limit): array;

    /**
     * Removes the events older than the instant, and the groups not seen since.
     *
     * @return int the events and groups removed
     */
    public function purge(DateTimeImmutable $before): int;
}
