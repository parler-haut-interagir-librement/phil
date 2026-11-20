<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Event;

/**
 * A token that opened the API. It is dispatched when the use is written, at most once per throttle interval of the
 * token, not on every request. It carries the token's identifier only: a listener reads the rest from the store.
 */
final readonly class TokenUsed
{
    public function __construct(
        public string $tokenId,
    ) {
    }
}
