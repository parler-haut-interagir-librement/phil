<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Event;

use Phil\IncidentMode\Activation;

/**
 * An incident ended: lifted by someone, or recorded as expired by the automatic lifting.
 */
final readonly class IncidentLifted
{
    public function __construct(
        public Activation $activation,
    ) {
    }
}
