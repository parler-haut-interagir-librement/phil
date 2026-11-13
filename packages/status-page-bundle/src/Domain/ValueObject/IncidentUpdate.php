<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\StatusPageBundle\Domain\Status\IncidentState;

/**
 * One message of an incident's thread: when it was written, the state it gives the incident, and what it tells
 * visitors.
 */
final readonly class IncidentUpdate
{
    /** @var non-empty-string */
    public string $message;

    public function __construct(
        public DateTimeImmutable $at,
        public IncidentState $state,
        string $message,
    ) {
        $this->message = Text::message($message, 'message of an update');
    }
}
