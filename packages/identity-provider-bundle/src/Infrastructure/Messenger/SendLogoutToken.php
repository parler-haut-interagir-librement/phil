<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Messenger;

/**
 * The message that sends one client the logout token of a session that ended: identifiers only, the token itself
 * signed when the message is handled.
 */
final readonly class SendLogoutToken
{
    public function __construct(
        public string $clientId,
        public string $accountId,
        public string $sessionId,
    ) {
    }
}
