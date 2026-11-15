<?php

declare(strict_types=1);

namespace Phil\Lti\Security;

use DateTimeImmutable;

/**
 * What the tool remembers between the login initiation and the launch: the `state` it sent, the `nonce` the `id_token`
 * must carry, the platform it asked, and the target of the launch.
 */
final readonly class LoginState
{
    public function __construct(
        public string $state,
        public string $nonce,
        public string $issuer,
        public string $clientId,
        public string $targetLinkUri,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
