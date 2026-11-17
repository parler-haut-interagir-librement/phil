<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Closure;
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

    /**
     * Applies a change to an account as it is stored now, read again and held so that no other request changes it
     * meanwhile: the change looks at the row read again and says whether it applies, and the row is saved only when it
     * does. Of two requests that read the same account at once — two failed sign-ins, two uses of one reset link —, the
     * second thus sees what the first wrote.
     *
     * @param Closure(Account): bool $change changes the account it is given, and only when it returns true
     *
     * @return bool whether the change applied; false as well when the account is no longer stored
     */
    public function apply(Account $account, Closure $change): bool;
}
