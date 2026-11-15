<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

/**
 * Where a ceremony keeps what it issued until its answer comes back — a WebAuthn challenge, a pending SMS code —, for
 * the person who started it only, and for one use: `take()` removes what it returns.
 */
interface ChallengeStore
{
    public function put(string $purpose, string $value): void;

    public function take(string $purpose): ?string;

    public function peek(string $purpose): ?string;
}
