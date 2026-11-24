<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceSubscriptionBundle\Application\Contract\DeliveryAddressRepository;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;

/**
 * `DeliveryAddressRepository` on the Doctrine ORM: a copy is one row of `phil_commerce_subscription_order_address`,
 * keyed by the order's number; the address of a subscription is its column `delivery_address`.
 *
 * `erase()` and `purge()` run DQL `UPDATE` and `DELETE` statements, which write at once and bump the version of each
 * subscription they change; entities already loaded in the unit of work are not refreshed.
 */
final readonly class DoctrineDeliveryAddressRepository implements DeliveryAddressRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $orderNumber): ?OrderAddress
    {
        return $this->entityManager->find(OrderAddress::class, $orderNumber);
    }

    #[Override]
    public function add(OrderAddress $copy): void
    {
        $this->entityManager->persist($copy);
    }

    #[Override]
    public function ofSubscriber(string $subscriber, int $limit): array
    {
        /** @var list<OrderAddress> */
        return $this->entityManager->createQueryBuilder()
            ->select('copy')
            ->from(OrderAddress::class, 'copy')
            ->where('copy.subscriber = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->orderBy('copy.frozenAt', 'ASC')
            ->addOrderBy('copy.orderNumber', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function erase(string $subscriber): int
    {
        $addresses = $this->entityManager->createQuery(\sprintf(
            'UPDATE %s s SET s.deliveryAddress = NULL, s.lastShippedAt = NULL, s.version = s.version + 1 WHERE s.subscriber = :subscriber AND (s.deliveryAddress IS NOT NULL OR s.lastShippedAt IS NOT NULL)',
            Subscription::class,
        ))->setParameter('subscriber', $subscriber)->execute();

        $copies = $this->entityManager->createQuery(\sprintf('DELETE FROM %s copy WHERE copy.subscriber = :subscriber', OrderAddress::class))
            ->setParameter('subscriber', $subscriber)
            ->execute();

        return $this->count($addresses) + $this->count($copies);
    }

    #[Override]
    public function purge(DateTimeImmutable $cutoff, int $limit): array
    {
        /** @var list<string> $subscriptions */
        $subscriptions = $this->entityManager->createQueryBuilder()
            ->select('s.id')
            ->from(Subscription::class, 's')
            ->where('s.state = :ended')
            ->andWhere('s.deliveryAddress IS NOT NULL')
            ->andWhere('s.endedAt < :cutoff')
            ->andWhere('s.lastShippedAt IS NULL OR s.lastShippedAt < :cutoff')
            ->setParameter('ended', SubscriptionState::Ended->value)
            ->setParameter('cutoff', $cutoff, Types::DATETIME_IMMUTABLE)
            ->orderBy('s.endedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        $addresses = 0;

        if ([] !== $subscriptions) {
            $addresses = $this->count($this->entityManager->createQuery(\sprintf(
                'UPDATE %s s SET s.deliveryAddress = NULL, s.lastShippedAt = NULL, s.version = s.version + 1 WHERE s.id IN (:ids) AND s.deliveryAddress IS NOT NULL',
                Subscription::class,
            ))->setParameter('ids', $subscriptions, ArrayParameterType::STRING)->execute());
        }

        /** @var list<string> $orders */
        $orders = $this->entityManager->createQueryBuilder()
            ->select('copy.orderNumber')
            ->from(OrderAddress::class, 'copy')
            ->where('copy.keptSince < :cutoff')
            ->setParameter('cutoff', $cutoff, Types::DATETIME_IMMUTABLE)
            ->orderBy('copy.keptSince', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        $copies = 0;

        if ([] !== $orders) {
            $copies = $this->count($this->entityManager->createQuery(\sprintf('DELETE FROM %s copy WHERE copy.orderNumber IN (:orders)', OrderAddress::class))
                ->setParameter('orders', $orders, ArrayParameterType::STRING)
                ->execute());
        }

        return ['addresses' => $addresses, 'copies' => $copies];
    }

    private function count(mixed $affected): int
    {
        return \is_int($affected) ? $affected : 0;
    }
}
