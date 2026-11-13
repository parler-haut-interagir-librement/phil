<?php

declare(strict_types=1);

namespace Phil\Automations\Run;

use Phil\Automations\Engine\Payload;

/**
 * An action step a run went through, with what it gave back: the steps after it read it, and its compensation undoes
 * it.
 */
final readonly class CompletedStep
{
    public function __construct(
        public string $stepId,
        public string $action,
        public Payload $result,
    ) {
    }
}
