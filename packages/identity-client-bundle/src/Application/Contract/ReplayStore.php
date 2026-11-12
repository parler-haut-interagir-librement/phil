<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Contract;

/**
 * Remembers the logout tokens that were already accepted.
 */
interface ReplayStore
{
    /**
     * Claims a token identifier for $ttl seconds, atomically across the servers of the application.
     *
     * @return bool false when the identifier is already claimed, or being claimed by another delivery
     */
    public function claim(string $tokenId, int $ttl): bool;
}
