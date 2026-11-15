<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;

/**
 * The authenticator applications and recovery codes of the accounts.
 */
interface SecondFactorRepository
{
    public function totp(string $accountId): ?TotpCredential;

    public function recoveryCodes(string $accountId): ?RecoveryCodeSet;

    public function save(TotpCredential|RecoveryCodeSet ...$factors): void;

    public function remove(TotpCredential|RecoveryCodeSet $factor): void;
}
