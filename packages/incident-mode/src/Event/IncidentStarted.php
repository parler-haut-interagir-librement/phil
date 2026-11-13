<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Event;

use Phil\IncidentMode\Activation;

/**
 * An incident was started: its restrictions are in force from now until its end.
 */
final readonly class IncidentStarted
{
    public function __construct(
        public Activation $activation,
    ) {
    }
}
