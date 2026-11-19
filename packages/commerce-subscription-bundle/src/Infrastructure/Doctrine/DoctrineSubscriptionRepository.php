<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;

/**
 * `SubscriptionRepository` on the Doctrine ORM: a subscription is one row of `phil_commerce_subscription`. Each read
 * of the sweep is a query of its own on the index `(state, period_end)`: the subscriptions over their period, the
 * pending ones to order, the pending ones to abandon.
 */
final readonly class DoctrineSubscriptionRepository implements SubscriptionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $id): ?Subscription
    {
        return $this->entityManager->find(Subscription::class, $id);
    }

    #[Override]
    public function forSubscriber(string $subscriber): array
    {
        return $this->entityManager->getRepository(Subscription::class)->findBy(['subscriber' => $subscriber], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function forSubscribers(array $subscribers): array
    {
        // An empty list reads nothing: Doctrine matches no row against an empty `IN`.
        return $this->entityManager->getRepository(Subscription::class)->findBy(['subscriber' => $subscribers], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function periodOverBy(DateTimeImmutable $at, ?int $limit = null): array
    {
        /** @var list<Subscription> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Subscription::class, 's')
            ->where('s.state IN (:running)')
            ->andWhere('s.periodEnd <= :at')
            ->setParameter('running', [SubscriptionState::Trialing, SubscriptionState::Active, SubscriptionState::PastDue])
            ->setParameter('at', $at)
            ->orderBy('s.periodEnd', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function pendingWithoutOrder(?int $limit = null): array
    {
        /** @var list<Subscription> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Subscription::class, 's')
            ->where('s.state = :pending')
            ->andWhere('s.awaitedOrder IS NULL')
            ->setParameter('pending', SubscriptionState::Pending)
            ->orderBy('s.createdAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function pendingCreatedBefore(DateTimeImmutable $before, ?int $limit = null): array
    {
        /** @var list<Subscription> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Subscription::class, 's')
            ->where('s.state = :pending')
            ->andWhere('s.createdAt < :before')
            ->setParameter('pending', SubscriptionState::Pending)
            ->setParameter('before', $before)
            ->orderBy('s.createdAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function add(Subscription $subscription): void
    {
        $this->entityManager->persist($subscription);
    }
}
