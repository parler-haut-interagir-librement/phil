<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Messenger;

use Phil\IncidentMode\IncidentMode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Lifts every incident past its end.
 */
#[AsMessageHandler]
final readonly class LiftExpiredIncidentsHandler
{
    public function __construct(
        private IncidentMode $incidents,
    ) {
    }

    public function __invoke(LiftExpiredIncidents $message): void
    {
        $this->incidents->liftExpired();
    }
}
