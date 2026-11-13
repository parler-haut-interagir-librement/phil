<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;

/**
 * An action that can undo what it did, when a step after it fails: remove the subscription it added, cancel the
 * reservation it made.
 *
 * A compensation may run twice, too: undoing what is already undone changes nothing.
 */
interface CompensableAction extends Action
{
    /**
     * @param Payload $result what `execute()` returned for this step
     */
    public function compensate(ActionContext $context, Payload $result): void;
}
