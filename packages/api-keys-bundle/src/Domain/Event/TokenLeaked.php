<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Event;

/**
 * A token revoked because it was found where it should not be: published in a repository and reported by a secret
 * scanner. It follows the `TokenRevoked` of the same token, for a listener that tells its owner. It carries the token's
 * identifier only: a listener reads the rest from the store.
 */
final readonly class TokenLeaked
{
    public function __construct(
        public string $tokenId,
    ) {
    }
}
