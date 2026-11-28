<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\WorkshopsBundle\Domain\Contract\SessionRepository;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use SortDirection;

/**
 * The sessions with Doctrine. Moments are compared as `datetime_utc_immutable`, the type of their columns: in UTC,
 * whatever the zone of the moment given.
 */
final readonly class DoctrineSessionRepository implements SessionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?WorkshopSession
    {
        return $this->entityManager->find(WorkshopSession::class, $id);
    }

    #[Override]
    public function save(WorkshopSession $session): void
    {
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    #[Override]
    public function ofWorkshop(Workshop $workshop, DateTimeImmutable $after, int $limit): array
    {
        /** @var list<WorkshopSession> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(WorkshopSession::class, 's')
            ->where('s.workshop = :workshop')
            ->andWhere('s.endsAt > :after')
            ->orderBy('s.startsAt', SortDirection::Ascending)
            ->addOrderBy('s.id', SortDirection::Ascending)
            ->setParameter('workshop', $workshop->id)
            ->setParameter('after', $after, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
