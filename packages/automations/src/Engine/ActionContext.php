<?php

declare(strict_types=1);

namespace Phil\Automations\Engine;

use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Options;
use Phil\Automations\Run\RunId;

/**
 * What an action is given for one step of one run.
 */
final readonly class ActionContext
{
    /**
     * @param array<string, Payload> $outputs        what each action step before this one gave back, by step id
     * @param string                 $idempotencyKey the same for every attempt of this step of this run: pass it on
     *                                               to the service called, or check it, so that a second attempt
     *                                               changes nothing
     * @param int                    $attempt        0 for the first attempt, then 1, 2…
     */
    public function __construct(
        public RunId $runId,
        public AutomationId $automationId,
        public string $stepId,
        public Options $options,
        public Payload $payload,
        public ?string $subject,
        public array $outputs,
        public string $idempotencyKey,
        public int $attempt = 0,
    ) {
    }
}
