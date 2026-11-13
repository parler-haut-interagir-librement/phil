<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Messenger;

use Phil\IncidentMode\IncidentMode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Lifts the incident the message names, if it is past its end; an incident extended since waits for the message its
 * extension scheduled.
 */
#[AsMessageHandler]
final readonly class LiftIncidentHandler
{
    public function __construct(
        private IncidentMode $incidents,
    ) {
    }

    public function __invoke(LiftIncident $message): void
    {
        $this->incidents->liftIfDue($message->id);
    }
}
