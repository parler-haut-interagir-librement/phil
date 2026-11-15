<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;

/**
 * The linked identities in `phil_identity_linked`; each save is flushed.
 */
final readonly class DoctrineLinkedIdentityRepository implements LinkedIdentityRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $provider, string $subject): ?LinkedIdentity
    {
        return $this->entityManager->find(LinkedIdentity::class, LinkedIdentity::idOf($provider, $subject));
    }

    #[Override]
    public function forAccount(string $accountId): array
    {
        return $this->entityManager->getRepository(LinkedIdentity::class)->findBy(['accountId' => $accountId], ['linkedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function save(LinkedIdentity ...$identities): void
    {
        foreach ($identities as $identity) {
            $this->entityManager->persist($identity);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(LinkedIdentity $identity): void
    {
        $this->entityManager->remove($identity);
        $this->entityManager->flush();
    }
}
