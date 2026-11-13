<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\ActionFailed;

/**
 * Something a step does: send a message, add to a list, call a webhook. A package registers its actions.
 *
 * An action may run more than once for one step — a worker stopped halfway, a retry —: it passes
 * `ActionContext::$idempotencyKey` on, or checks it, so that the second run changes nothing. It keeps no state between
 * calls.
 */
interface Action
{
    public function describe(): ComponentDescription;

    /**
     * Does the work and says what it did, in identifiers, for the steps after it and for its compensation.
     *
     * @throws ActionFailed when the work could not be done; any other exception counts as a retryable failure
     */
    public function execute(ActionContext $context): Payload;
}
