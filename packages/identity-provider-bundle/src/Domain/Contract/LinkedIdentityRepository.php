<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;

/**
 * The external identities linked to the accounts.
 */
interface LinkedIdentityRepository
{
    public function find(string $provider, string $subject): ?LinkedIdentity;

    /**
     * @return list<LinkedIdentity> oldest first
     */
    public function forAccount(string $accountId): array;

    public function save(LinkedIdentity ...$identities): void;

    public function remove(LinkedIdentity $identity): void;
}
