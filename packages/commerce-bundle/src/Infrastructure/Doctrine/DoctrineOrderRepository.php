<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;
use Phil\CommerceBundle\Domain\Contract\ExpiringOrders;
use Phil\CommerceBundle\Domain\Model\DeferredSale;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\PaymentLink;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;

/**
 * `OrderRepository` on the Doctrine ORM: an order is one row of `phil_commerce_order`, found by its number.
 *
 * As `ExpiringOrders`, it reads the orders nobody paid on the index of the state and the moment placed, and deletes an
 * expired order in one transaction, once it checked again that no payment ever held money for it and that it has no
 * receipt.
 */
final readonly class DoctrineOrderRepository implements OrderRepository, ExpiringOrders
{
    /** The states of a payment that never held money: an order whose payments are all in these is unpaid. */
    private const array EMPTY_HANDED = [PaymentState::Failed->value, PaymentState::Cancelled->value, PaymentState::Abandoned->value];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $number): ?Order
    {
        return $this->entityManager->find(Order::class, $number);
    }

    #[Override]
    public function add(Order $order): void
    {
        $this->entityManager->persist($order);
    }

    #[Override]
    public function unpaidPlacedBefore(?array $channels, array $except, DateTimeImmutable $before, int $limit): array
    {
        $query = $this->unpaid($channels, $except)
            ->select('o')
            ->andWhere('o.state = :placed', 'o.placedAt < :before')
            ->andWhere(\sprintf('NOT EXISTS (SELECT d.orderNumber FROM %s d WHERE d.orderNumber = o.number)', DeferredSale::class))
            ->orderBy('o.placedAt')
            ->addOrderBy('o.number')
            ->setMaxResults(max(1, $limit))
            ->setParameter('placed', OrderState::Placed->value)
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE);

        $orders = [];
        $result = $query->getQuery()->getResult();

        foreach (\is_array($result) ? $result : [] as $order) {
            if ($order instanceof Order) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    #[Override]
    public function expiredBefore(?array $channels, array $except, DateTimeImmutable $before, int $limit): array
    {
        $numbers = $this->deletable($this->unpaid($channels, $except))
            ->select('o.number')
            ->andWhere('o.expiredAt < :before')
            ->orderBy('o.expiredAt')
            ->addOrderBy('o.number')
            ->setMaxResults(max(1, $limit))
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($numbers, is_string(...)));
    }

    #[Override]
    public function deleteExpired(string $orderNumber): void
    {
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($orderNumber): void {
            // Checked again inside the transaction: a payment that captured money meanwhile keeps the order.
            $still = $this->deletable($this->unpaid(null, []))
                ->select('o.number')
                ->andWhere('o.number = :number')
                ->setParameter('number', $orderNumber)
                ->getQuery()
                ->getSingleColumnResult();

            if ([] === $still) {
                return;
            }

            foreach ([Payment::class, PaymentLink::class, OrderChange::class, OrderStateChange::class] as $class) {
                $entityManager->createQueryBuilder()
                    ->delete($class, 'x')
                    ->where('x.orderNumber = :number')
                    ->setParameter('number', $orderNumber)
                    ->getQuery()
                    ->execute();
            }

            $known = $entityManager->getUnitOfWork()->tryGetById($orderNumber, Order::class);

            if ($known instanceof Order) {
                $entityManager->detach($known);
            }

            $entityManager->createQueryBuilder()
                ->delete(Order::class, 'o')
                ->where('o.number = :number')
                ->setParameter('number', $orderNumber)
                ->getQuery()
                ->execute();
        });
    }

    /**
     * The orders of the channels without a payment that ever held money.
     *
     * @param list<string>|null $channels
     * @param list<string>      $except
     */
    private function unpaid(?array $channels, array $except): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Order::class, 'o')
            ->where(\sprintf('NOT EXISTS (SELECT p.id FROM %s p WHERE p.orderNumber = o.number AND p.state NOT IN (:empty_handed))', Payment::class))
            ->setParameter('empty_handed', self::EMPTY_HANDED, ArrayParameterType::STRING);

        if (null !== $channels) {
            return $query->andWhere('o.channel IN (:channels)')->setParameter('channels', $channels, ArrayParameterType::STRING);
        }

        if ([] !== $except) {
            return $query->andWhere('o.channel IS NULL OR o.channel NOT IN (:except)')->setParameter('except', $except, ArrayParameterType::STRING);
        }

        return $query;
    }

    private function deletable(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->andWhere('o.expiredAt IS NOT NULL')
            ->andWhere(\sprintf('NOT EXISTS (SELECT r.number FROM %s r WHERE r.orderNumber = o.number)', Receipt::class));
    }
}
