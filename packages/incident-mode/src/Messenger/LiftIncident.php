<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Messenger;

/**
 * Lift this incident if it is past its end: sent delayed until its end, carrying its identifier only.
 */
final readonly class LiftIncident
{
    public function __construct(
        public string $id,
    ) {
    }
}
