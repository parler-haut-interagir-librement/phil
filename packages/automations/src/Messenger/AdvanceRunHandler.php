<?php

declare(strict_types=1);

namespace Phil\Automations\Messenger;

use Phil\Automations\Engine\AutomationEngine;
use Phil\Automations\Run\RunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Takes a run on, on a worker.
 */
#[AsMessageHandler]
final readonly class AdvanceRunHandler
{
    public function __construct(
        private AutomationEngine $engine,
    ) {
    }

    public function __invoke(AdvanceRun $message): void
    {
        $this->engine->advance(RunId::fromString($message->runId));
    }
}
