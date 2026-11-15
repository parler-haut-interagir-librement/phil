<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Contract\OrderJournal;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;

/**
 * `OrderJournal` on the Doctrine ORM: one row of `phil_commerce_order_state_change` per move, never updated.
 */
final readonly class DoctrineOrderJournal implements OrderJournal
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function record(OrderStateChange $change): void
    {
        $this->entityManager->persist($change);
    }

    #[Override]
    public function movesOf(string $orderNumber, int $limit = 100): array
    {
        return $this->entityManager->getRepository(OrderStateChange::class)->findBy(['orderNumber' => $orderNumber], ['occurredAt' => 'ASC', 'id' => 'ASC'], max(1, $limit));
    }
}
