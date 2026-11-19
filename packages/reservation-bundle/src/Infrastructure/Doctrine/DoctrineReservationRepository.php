<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Infrastructure\Doctrine;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Contract\ReservationRepository;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use SortDirection;
use Throwable;

/**
 * The reservations with Doctrine. Moments are compared as `datetime_utc_immutable`, the type of their columns: in UTC,
 * whatever the zone of the moment given. `exclusively()` runs its work in a transaction that first updates the
 * resource's row: the database holds the other requests on the resource until it commits. A refusal rolls the
 * transaction back and leaves the entity manager open.
 */
final readonly class DoctrineReservationRepository implements ReservationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Reservation
    {
        return $this->entityManager->find(Reservation::class, $id);
    }

    #[Override]
    public function save(Reservation $reservation): void
    {
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();
    }

    #[Override]
    public function holding(BookableResource $resource, Interval $span, DateTimeImmutable $now): array
    {
        /** @var list<Reservation> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.resource = :resource')
            ->andWhere('r.startsAt < :until')
            ->andWhere('r.endsAt > :from')
            ->andWhere('r.status = :confirmed OR (r.status = :held AND r.holdExpiresAt > :now)')
            ->orderBy('r.startsAt', SortDirection::Ascending)
            ->addOrderBy('r.id', SortDirection::Ascending)
            ->setParameter('resource', $resource->id)
            ->setParameter('from', $span->start, Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('until', $span->end, Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('now', $now, Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('confirmed', ReservationStatus::Confirmed->value)
            ->setParameter('held', ReservationStatus::Held->value)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function between(DateTimeImmutable $from, DateTimeImmutable $until, int $limit, ?BookableResource $resource = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.startsAt < :until')
            ->andWhere('r.endsAt > :from')
            ->andWhere('r.status IN (:live)')
            ->orderBy('r.startsAt', SortDirection::Ascending)
            ->addOrderBy('r.id', SortDirection::Ascending)
            ->setParameter('from', $from, Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('until', $until, Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('live', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
            ->setMaxResults($limit);

        if ($resource instanceof BookableResource) {
            $query->andWhere('r.resource = :resource')->setParameter('resource', $resource->id);
        }

        /** @var list<Reservation> */
        return $query->getQuery()->getResult();
    }

    #[Override]
    public function expiredHolds(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Reservation> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.status = :held')
            ->andWhere('r.holdExpiresAt <= :now')
            ->orderBy('r.holdExpiresAt', SortDirection::Ascending)
            ->addOrderBy('r.id', SortDirection::Ascending)
            ->setParameter('held', ReservationStatus::Held->value)
            ->setParameter('now', $now, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function upcoming(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): array
    {
        /** @var list<Reservation> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Reservation::class, 'r')
            ->where('r.status = :confirmed')
            ->andWhere('r.startsAt > :now')
            ->andWhere('r.startsAt <= :until')
            ->orderBy('r.startsAt', SortDirection::Ascending)
            ->addOrderBy('r.id', SortDirection::Ascending)
            ->setParameter('confirmed', ReservationStatus::Confirmed->value)
            ->setParameter('now', $now, Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('until', $until, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function exclusively(BookableResource $resource, Closure $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('UPDATE '.BookableResource::TABLE.' SET claims = claims + 1 WHERE id = ?', [$resource->id]);
            $result = $work();
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $connection->commit();

        return $result;
    }
}
