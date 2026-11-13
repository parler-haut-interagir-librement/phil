<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Contract;

use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;

/**
 * Where the groups and their events are kept. Everything it receives has gone through the scrubber.
 */
interface ErrorStore
{
    /**
     * The group of a fingerprint, or `null` when none is kept.
     */
    public function find(string $fingerprint): ?ErrorGroup;

    /**
     * Writes the group, created or updated, and keeps the event with it when one is given; a store may keep a
     * bounded number of events per group.
     */
    public function save(ErrorGroup $group, ?ErrorEvent $event = null): void;
}
