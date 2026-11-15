<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;

/**
 * The passkeys in `phil_identity_passkey`; each save is flushed.
 */
final readonly class DoctrinePasskeyRepository implements PasskeyRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findByCredentialId(string $credentialId): ?Passkey
    {
        return $this->entityManager->find(Passkey::class, Passkey::idOf($credentialId));
    }

    #[Override]
    public function forAccount(string $accountId): array
    {
        return $this->entityManager->getRepository(Passkey::class)->findBy(['accountId' => $accountId], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function save(Passkey ...$passkeys): void
    {
        foreach ($passkeys as $passkey) {
            $this->entityManager->persist($passkey);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Passkey $passkey): void
    {
        $this->entityManager->remove($passkey);
        $this->entityManager->flush();
    }
}
