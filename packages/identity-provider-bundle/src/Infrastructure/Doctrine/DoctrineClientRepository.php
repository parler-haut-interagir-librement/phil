<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;

/**
 * The clients in `phil_identity_client` and the consents in `phil_identity_consent`; each save is flushed.
 */
final readonly class DoctrineClientRepository implements ClientRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Client
    {
        return $this->entityManager->find(Client::class, $id);
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(Client::class)->findBy([], ['id' => 'ASC']);
    }

    #[Override]
    public function save(Client ...$clients): void
    {
        foreach ($clients as $client) {
            $this->entityManager->persist($client);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Client $client): void
    {
        $this->entityManager->remove($client);
        $this->entityManager->flush();
    }

    #[Override]
    public function consent(string $accountId, string $clientId): ?Consent
    {
        return $this->entityManager->find(Consent::class, Consent::idOf($accountId, $clientId));
    }

    #[Override]
    public function consentsOf(string $accountId): array
    {
        return $this->entityManager->getRepository(Consent::class)->findBy(['accountId' => $accountId], ['grantedAt' => 'DESC', 'id' => 'ASC']);
    }

    #[Override]
    public function saveConsent(Consent $consent): void
    {
        $this->entityManager->persist($consent);
        $this->entityManager->flush();
    }

    #[Override]
    public function removeConsent(Consent $consent): void
    {
        $this->entityManager->remove($consent);
        $this->entityManager->flush();
    }
}
