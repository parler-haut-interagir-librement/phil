<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Security\LoginState;

/**
 * The login states of the tool. A state is taken once: the second launch with the same state finds nothing.
 */
interface LoginStates
{
    public function save(LoginState $state): void;

    /**
     * The state, removed in the same step, or null when it is unknown, already taken or expired.
     */
    public function take(string $state): ?LoginState;
}
