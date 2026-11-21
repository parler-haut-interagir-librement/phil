<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Application;

use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;

/**
 * The account a provider's subject is linked to, and the closing of its sessions through the bundle's `Sessions` —
 * which revokes the tokens the bundle issued in them. Without the bundle's `sessions` module, nothing is closed.
 */
final readonly class LinkedSessions
{
    public function __construct(
        private LinkedIdentityRepository $identities,
        private AccountRepository $accounts,
        private ?Sessions $sessions = null,
    ) {
    }

    /**
     * The identifier of the account the subject is linked to; null when it is linked to none.
     */
    public function accountOf(string $provider, string $subject): ?string
    {
        $linked = $this->identities->find($provider, $subject);

        return $linked instanceof LinkedIdentity ? $linked->accountId() : null;
    }

    /**
     * @return int the sessions closed
     */
    public function close(string $accountId): int
    {
        $account = $this->accounts->get($accountId);

        return $account instanceof Account && $this->sessions instanceof Sessions ? $this->sessions->closeAll($account) : 0;
    }
}
