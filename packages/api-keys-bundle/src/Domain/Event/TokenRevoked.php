<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Event;

/**
 * A token revoked, by its owner, a command or the rotation that replaced it. It carries the token's identifier only: a listener reads the rest from the store.
 */
final readonly class TokenRevoked
{
    public function __construct(
        public string $tokenId,
    ) {
    }
}
