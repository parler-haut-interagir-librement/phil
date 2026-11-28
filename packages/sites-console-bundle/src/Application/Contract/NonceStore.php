<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;

/**
 * The nonces an agent has accepted, kept while their requests could still be replayed. Remembering a nonce is one
 * atomic step: of two requests with the same nonce, at most one is told it is new, whatever the number of servers.
 */
interface NonceStore
{
    /**
     * Keeps the nonce for that many seconds.
     *
     * @return bool false when it was already kept: the request is a replay
     *
     * @throws StateNotKept when the nonce could not be kept
     */
    public function remember(string $nonce, int $seconds): bool;
}
