<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Closure;
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

    /**
     * Applies a change to a factor as it is stored now, read again and held so that no other request changes it
     * meanwhile: the change looks at the row read again and says whether it applies, and the row is saved only when it
     * does. Of two requests that read the same factor at once, the second thus sees what the first wrote, and a code
     * is accepted once.
     *
     * @template T of TotpCredential|RecoveryCodeSet
     *
     * @param T                $factor
     * @param Closure(T): bool $change changes the factor it is given, and only when it returns true
     *
     * @return bool whether the change applied; false as well when the factor is no longer stored
     */
    public function apply(TotpCredential|RecoveryCodeSet $factor, Closure $change): bool;
}
