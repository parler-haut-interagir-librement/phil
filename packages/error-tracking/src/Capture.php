<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * What became of an event the tracker kept: its identifier, the fingerprint of its group, what it was to the group,
 * and the status the group is left in.
 */
final readonly class Capture
{
    public function __construct(
        public string $eventId,
        public string $fingerprint,
        public Occurrence $occurrence,
        public GroupStatus $status,
    ) {
    }
}
