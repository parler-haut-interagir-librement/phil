<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;

/**
 * The signing keys in `phil_identity_signing_key`; each save is flushed.
 */
final readonly class DoctrineSigningKeyRepository implements SigningKeyRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(SigningKey::class)->findBy([], ['createdAt' => 'ASC', 'kid' => 'ASC']);
    }

    #[Override]
    public function get(string $kid): ?SigningKey
    {
        return $this->entityManager->find(SigningKey::class, $kid);
    }

    #[Override]
    public function save(SigningKey ...$keys): void
    {
        foreach ($keys as $key) {
            $this->entityManager->persist($key);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(SigningKey $key): void
    {
        $this->entityManager->remove($key);
        $this->entityManager->flush();
    }
}
