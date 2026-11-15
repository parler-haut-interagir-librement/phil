<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;

/**
 * The clients of the provider and the consents the accounts gave them.
 */
interface ClientRepository
{
    public function get(string $id): ?Client;

    /**
     * @return list<Client> by identifier
     */
    public function all(): array;

    public function save(Client ...$clients): void;

    public function remove(Client $client): void;

    public function consent(string $accountId, string $clientId): ?Consent;

    /**
     * @return list<Consent> the latest first
     */
    public function consentsOf(string $accountId): array;

    public function saveConsent(Consent $consent): void;

    public function removeConsent(Consent $consent): void;
}
