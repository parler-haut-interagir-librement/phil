<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Event;

use DateTimeImmutable;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;

/**
 * An incident in force was given a later end.
 */
final readonly class IncidentExtended
{
    public function __construct(
        public Activation $activation,
        public DateTimeImmutable $previousEnd,
        public ?Actor $by,
    ) {
    }
}
