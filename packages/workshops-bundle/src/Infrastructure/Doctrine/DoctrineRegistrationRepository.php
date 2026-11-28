<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use SortDirection;
use Throwable;

/**
 * The registrations with Doctrine. `exclusively()` runs its work in a transaction that first updates the session's
 * row, then reads the session again: the database holds the other requests on the session until it commits, and the
 * work sees the capacity and the status another request may have changed. A refusal rolls the transaction back and
 * leaves the entity manager open.
 */
final readonly class DoctrineRegistrationRepository implements RegistrationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Registration
    {
        return $this->entityManager->find(Registration::class, $id);
    }

    #[Override]
    public function save(Registration $registration): void
    {
        $this->entityManager->persist($registration);
        $this->entityManager->flush();
    }

    #[Override]
    public function live(WorkshopSession $session, string $participant): ?Registration
    {
        /** @var ?Registration */
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Registration::class, 'r')
            ->where('r.session = :session')
            ->andWhere('r.participant = :participant')
            ->andWhere('r.status = :live')
            ->setParameter('session', $session->id)
            ->setParameter('participant', $participant)
            ->setParameter('live', RegistrationStatus::Confirmed->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[Override]
    public function seats(WorkshopSession $session, RegistrationStatus $status): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(r.seats), 0)')
            ->from(Registration::class, 'r')
            ->where('r.session = :session')
            ->andWhere('r.status = :status')
            ->setParameter('session', $session->id)
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Override]
    public function ofSession(WorkshopSession $session, int $limit, ?RegistrationStatus $status = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Registration::class, 'r')
            ->where('r.session = :session')
            ->orderBy('r.createdAt', SortDirection::Ascending)
            ->addOrderBy('r.id', SortDirection::Ascending)
            ->setParameter('session', $session->id)
            ->setMaxResults($limit);

        if ($status instanceof RegistrationStatus) {
            $query->andWhere('r.status = :status')->setParameter('status', $status->value);
        }

        /** @var list<Registration> */
        return $query->getQuery()->getResult();
    }

    #[Override]
    public function exclusively(WorkshopSession $session, Closure $work): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('UPDATE '.WorkshopSession::TABLE.' SET claims = claims + 1 WHERE id = ?', [$session->id]);
            $this->entityManager->refresh($session);
            $result = $work();
        } catch (Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $connection->commit();

        return $result;
    }
}
