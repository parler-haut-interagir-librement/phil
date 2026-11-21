<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Application\Contract;

/**
 * The subjects of a provider that may not sign in for now: an account the provider disabled because it was hijacked,
 * until the provider enables it again. A store that forgets — a cache pool that evicts — lets a held subject in again,
 * so the store is durable.
 */
interface SubjectHolds
{
    public function hold(string $provider, string $subject): void;

    public function release(string $provider, string $subject): void;

    public function isHeld(string $provider, string $subject): bool;
}
