<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;

/**
 * The passkeys of the accounts.
 */
interface PasskeyRepository
{
    /**
     * The passkey of a credential identifier, raw bytes.
     */
    public function findByCredentialId(string $credentialId): ?Passkey;

    /**
     * @return list<Passkey> oldest first
     */
    public function forAccount(string $accountId): array;

    public function save(Passkey ...$passkeys): void;

    public function remove(Passkey $passkey): void;
}
