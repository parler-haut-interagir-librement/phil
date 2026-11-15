<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

/**
 * The accounts.
 */
interface AccountRepository
{
    public function get(string $id): ?Account;

    /**
     * The account of an address, normalized as `Account::normalizeEmail()` does.
     */
    public function findByEmail(string $email): ?Account;

    public function save(Account ...$accounts): void;

    public function remove(Account $account): void;
}
