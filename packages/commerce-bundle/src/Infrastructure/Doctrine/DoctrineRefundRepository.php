<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Contract\RefundRepository;
use Phil\CommerceBundle\Domain\Model\Refund;

/**
 * `RefundRepository` on the Doctrine ORM: a refund is one row of `phil_commerce_refund`.
 */
final readonly class DoctrineRefundRepository implements RefundRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findRefund(string $id): ?Refund
    {
        return $this->entityManager->find(Refund::class, $id);
    }

    #[Override]
    public function refundsOf(string $paymentId): array
    {
        return $this->entityManager->getRepository(Refund::class)->findBy(['paymentId' => $paymentId], ['grantedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function addRefund(Refund $refund): void
    {
        $this->entityManager->persist($refund);
    }
}
