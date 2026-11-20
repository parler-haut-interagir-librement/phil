<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;

/**
 * The addresses of the accounts for purposes other than signing in.
 */
interface AccountAddressRepository
{
    /**
     * @return list<AccountAddress>
     */
    public function forAccount(string $accountId): array;

    public function findByCanonical(AddressPurpose $purpose, string $canonical): ?AccountAddress;

    public function save(AccountAddress ...$addresses): void;

    public function remove(AccountAddress $address): void;
}
