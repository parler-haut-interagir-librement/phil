<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SeatRepository;

/**
 * `SeatRepository` on the Doctrine ORM: a seat is one row of `phil_commerce_subscription_seat`, keyed by the
 * subscription and the member. Whether a member holds one of the first seats reads their seat and counts the seats
 * assigned before it, whatever the number of seats.
 */
final readonly class DoctrineSeatRepository implements SeatRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $subscriptionId, string $member): ?Seat
    {
        return $this->entityManager->find(Seat::class, ['subscriptionId' => $subscriptionId, 'member' => $member]);
    }

    #[Override]
    public function holders(string $subscriptionId, ?int $limit = null): array
    {
        /** @var list<Seat> */
        return $this->entityManager->createQueryBuilder()
            ->select('seat')
            ->from(Seat::class, 'seat')
            ->where('seat.subscriptionId = :subscription')
            ->setParameter('subscription', $subscriptionId)
            ->orderBy('seat.assignedAt', 'ASC')
            ->addOrderBy('seat.member', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function count(string $subscriptionId): int
    {
        return $this->entityManager->getRepository(Seat::class)->count(['subscriptionId' => $subscriptionId]);
    }

    #[Override]
    public function holdsSeat(string $subscriptionId, string $member, int $seats): bool
    {
        $seat = $this->find($subscriptionId, $member);

        if (!$seat instanceof Seat) {
            return false;
        }

        $before = $this->entityManager->createQueryBuilder()
            ->select('COUNT(seat.member)')
            ->from(Seat::class, 'seat')
            ->where('seat.subscriptionId = :subscription')
            ->andWhere('seat.assignedAt < :at OR (seat.assignedAt = :at AND seat.member < :member)')
            ->setParameter('subscription', $subscriptionId)
            ->setParameter('at', $seat->assignedAt)
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $before < $seats;
    }

    #[Override]
    public function add(Seat $seat): void
    {
        $this->entityManager->persist($seat);
    }

    #[Override]
    public function remove(Seat $seat): void
    {
        $this->entityManager->remove($seat);
    }
}
