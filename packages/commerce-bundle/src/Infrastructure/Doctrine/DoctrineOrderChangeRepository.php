<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Contract\OrderChangeRepository;
use Phil\CommerceBundle\Domain\Model\OrderChange;

/**
 * `OrderChangeRepository` on the Doctrine ORM: a change is one row of `phil_commerce_order_change`.
 */
final readonly class DoctrineOrderChangeRepository implements OrderChangeRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findOrderChange(string $id): ?OrderChange
    {
        return $this->entityManager->find(OrderChange::class, $id);
    }

    #[Override]
    public function changesOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(OrderChange::class)->findBy(['orderNumber' => $orderNumber], ['preparedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function addOrderChange(OrderChange $change): void
    {
        $this->entityManager->persist($change);
    }
}
