<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Contract;

use Closure;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;

/**
 * Lets one process at a time refresh a given OAuth token. Many services rotate the refresh token at each use and
 * revoke every token of a grant whose old refresh token is used again: two processes that refresh the same token at
 * once could end the grant. Without a lock, a compare-and-swap on the row still keeps one refreshed token, but both
 * processes call the service.
 */
interface RefreshLock
{
    /**
     * Runs `$critical` while no other process runs it for the same secret, and returns what it returns.
     *
     * @template T
     *
     * @param Closure(): T $critical
     *
     * @return T
     */
    public function synchronized(Owner $owner, SecretName $name, Closure $critical): mixed;
}
