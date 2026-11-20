<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;

/**
 * `GiftRepository` on the Doctrine ORM: a gift is one row of `phil_commerce_subscription_gift`, found by its code
 * through the unique index on `code_hash`.
 */
final readonly class DoctrineGiftRepository implements GiftRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $id): ?Gift
    {
        return $this->entityManager->find(Gift::class, $id);
    }

    #[Override]
    public function findByCodeHash(string $hash): ?Gift
    {
        return $this->entityManager->getRepository(Gift::class)->findOneBy(['codeHash' => $hash]);
    }

    #[Override]
    public function add(Gift $gift): void
    {
        $this->entityManager->persist($gift);
    }
}
