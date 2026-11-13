<?php

declare(strict_types=1);

namespace Phil\Automations\Messenger;

use Phil\Automations\Engine\AutomationEngine;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends an `AdvanceRun` for each run whose time has come.
 */
#[AsMessageHandler]
final readonly class ResumeDueRunsHandler
{
    public function __construct(
        private AutomationEngine $engine,
    ) {
    }

    public function __invoke(ResumeDueRuns $message): void
    {
        $this->engine->resumeDue($message->limit);
    }
}
