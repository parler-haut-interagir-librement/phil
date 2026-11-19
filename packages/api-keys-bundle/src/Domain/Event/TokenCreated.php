<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Event;

/**
 * A token created, by its owner or a command, or as the successor of a rotated one. It carries the token's identifier only: a listener reads the rest from the store.
 */
final readonly class TokenCreated
{
    public function __construct(
        public string $tokenId,
    ) {
    }
}
