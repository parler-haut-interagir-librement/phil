<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Contract\DeferredSaleRepository;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Deferred\DeferredSaleState;
use Phil\CommerceBundle\Domain\Model\DeferredSale;

/**
 * Deferred sales through Doctrine: one sale through the ORM, the list of balances due through DBAL, which reads only
 * the order numbers, on the index of the state and the due date.
 */
final readonly class DoctrineDeferredSaleRepository implements DeferredSaleRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findDeferredSale(string $orderNumber): ?DeferredSale
    {
        return $this->entityManager->find(DeferredSale::class, $orderNumber);
    }

    #[Override]
    public function addDeferredSale(DeferredSale $sale): void
    {
        $this->entityManager->persist($sale);
    }

    #[Override]
    public function dueForBalance(DateTimeImmutable $at, int $limit): array
    {
        $numbers = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('order_number')
            ->from('phil_commerce_deferred_sale')
            ->where('state = :state', 'balance_trigger = :trigger', 'due_at <= :at')
            ->orderBy('due_at')
            ->addOrderBy('order_number')
            ->setMaxResults($limit)
            ->setParameter('state', DeferredSaleState::AwaitingBalance->value)
            ->setParameter('trigger', BalanceTrigger::Date->value)
            ->setParameter('at', $at, Types::DATETIME_IMMUTABLE)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_filter($numbers, is_string(...)));
    }
}
