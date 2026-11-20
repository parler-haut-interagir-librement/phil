<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

/**
 * Tells the clients that a session ended, once it is closed: the bundle's adapter sends each client that registered a
 * back-channel logout address a logout token, through Messenger, so that a client that does not answer is tried again.
 */
interface LogoutNotifier
{
    /**
     * @param list<string> $clientIds the clients the provider issued tokens to in the session, each once
     */
    public function sessionEnded(string $sessionId, string $accountId, array $clientIds): void;
}
