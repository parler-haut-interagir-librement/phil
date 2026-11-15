<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use Override;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The clients and consents in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryClientRepository implements ClientRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, Client> */
    private array $clients = [];

    /** @var array<string, Consent> */
    private array $consents = [];

    #[Override]
    public function get(string $id): ?Client
    {
        return self::copy($this->clients[$id] ?? null);
    }

    #[Override]
    public function all(): array
    {
        $clients = $this->clients;
        ksort($clients, \SORT_STRING);

        return self::copies(array_values($clients));
    }

    #[Override]
    public function save(Client ...$clients): void
    {
        foreach ($clients as $client) {
            $this->clients[$client->id()] = clone $client;
        }
    }

    #[Override]
    public function remove(Client $client): void
    {
        unset($this->clients[$client->id()]);
    }

    #[Override]
    public function consent(string $accountId, string $clientId): ?Consent
    {
        return self::copy($this->consents[Consent::idOf($accountId, $clientId)] ?? null);
    }

    #[Override]
    public function consentsOf(string $accountId): array
    {
        $consents = array_filter($this->consents, static fn (Consent $consent): bool => $consent->accountId() === $accountId);
        usort($consents, static fn (Consent $a, Consent $b): int => [$b->grantedAt(), $a->id()] <=> [$a->grantedAt(), $b->id()]);

        return self::copies($consents);
    }

    #[Override]
    public function saveConsent(Consent $consent): void
    {
        $this->consents[$consent->id()] = clone $consent;
    }

    #[Override]
    public function removeConsent(Consent $consent): void
    {
        unset($this->consents[$consent->id()]);
    }

    #[Override]
    public function reset(): void
    {
        $this->clients = [];
        $this->consents = [];
    }
}
