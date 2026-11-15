<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;

/**
 * The signed-in sessions of the accounts.
 */
interface AccountSessionRepository
{
    public function get(string $id): ?AccountSession;

    /**
     * @return list<AccountSession> the sessions not closed, the last seen first
     */
    public function openFor(string $accountId): array;

    /**
     * Whether the account has signed in before, on any device.
     */
    public function hasAny(string $accountId): bool;

    /**
     * Whether the account has signed in on this device before.
     */
    public function knowsDevice(string $accountId, string $device): bool;

    public function save(AccountSession ...$sessions): void;

    /**
     * Removes the sessions closed or last seen before the date.
     *
     * @return int the sessions removed
     */
    public function purge(DateTimeImmutable $before): int;
}
