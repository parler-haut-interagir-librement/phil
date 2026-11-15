<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Repository\PaymentRepository;

/**
 * `PaymentRepository` on the Doctrine ORM: a payment is one row of `phil_commerce_payment`, found by its id, by its
 * method and the processor's reference (a unique pair), or by its order (an index).
 */
final readonly class DoctrinePaymentRepository implements PaymentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $id): ?Payment
    {
        return $this->entityManager->find(Payment::class, $id);
    }

    #[Override]
    public function findByExternalReference(string $method, string $externalReference): ?Payment
    {
        return $this->entityManager->getRepository(Payment::class)->findOneBy(['method' => $method, 'externalReference' => $externalReference]);
    }

    #[Override]
    public function forOrder(string $orderNumber): array
    {
        return $this->entityManager->getRepository(Payment::class)->findBy(['orderNumber' => $orderNumber], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function add(Payment $payment): void
    {
        $this->entityManager->persist($payment);
    }
}
