<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use Override;
use Phil\CommerceBundle\Application\Contract\PaymentLock;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Refund;

/**
 * `PaymentLock` on the row of `phil_commerce_payment`, locked for writing until the transaction ends — `SELECT … FOR
 * UPDATE` on MySQL, MariaDB and PostgreSQL —: a second refund granted at once on the same payment waits until the
 * first one's transaction is committed or rolled back.
 *
 * The lock reads the payment's version: when another transaction changed the payment since the entity manager read
 * it — a processor's notification applied meanwhile —, Doctrine's `OptimisticLockException` is thrown, and the
 * application reads the payment again before granting. The refunds are read by a locking read too, which sees what
 * other transactions committed even under the repeatable reads of MySQL, where a plain `SELECT` keeps the snapshot of
 * the transaction's first read; a refund the entity manager already holds keeps the state it holds.
 */
final readonly class DoctrinePaymentLock implements PaymentLock
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws OptimisticLockException when the payment changed since it was read
     */
    #[Override]
    public function lockForRefund(Payment $payment): array
    {
        if (!$this->entityManager->getConnection()->isTransactionActive()) {
            throw new LogicException('A refund is granted inside a transaction, committed or rolled back with the refund: wrap the grant in EntityManagerInterface::wrapInTransaction().');
        }

        // The ORM converts the version by its mapping, so that both are integers.
        /** @var int $stored */
        $stored = $this->entityManager->createQuery(\sprintf('SELECT p.version FROM %s p WHERE p.id = :payment', Payment::class))
            ->setParameter('payment', $payment->id)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getSingleScalarResult();
        /** @var int $held */
        $held = $this->entityManager->getClassMetadata(Payment::class)->getFieldValue($payment, 'version');

        if ($stored !== $held) {
            throw OptimisticLockException::lockFailedVersionMismatch($payment, $held, $stored);
        }

        /** @var list<Refund> */
        return $this->entityManager->createQuery(\sprintf('SELECT r FROM %s r WHERE r.paymentId = :payment ORDER BY r.grantedAt ASC, r.id ASC', Refund::class))
            ->setParameter('payment', $payment->id)
            ->setLockMode(LockMode::PESSIMISTIC_READ)
            ->getResult();
    }
}
