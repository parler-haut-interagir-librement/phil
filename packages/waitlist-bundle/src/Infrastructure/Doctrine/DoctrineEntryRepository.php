<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Override;
use Phil\WaitlistBundle\Domain\Contract\EntryRepository;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;

/**
 * The entries with Doctrine. Each query refreshes the entries it reads, so that a request under a list's lock sees
 * what the requests before it saved, not what its entity manager read earlier.
 */
final readonly class DoctrineEntryRepository implements EntryRepository
{
    /** The type the moments are compared as, the type of their columns. */
    private const string MOMENT = 'datetime_utc_immutable';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Entry
    {
        return $this->entityManager->find(Entry::class, $id);
    }

    #[Override]
    public function save(Entry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    #[Override]
    public function refresh(Entry $entry): void
    {
        $this->entityManager->refresh($entry);
    }

    #[Override]
    public function live(Waitlist $waitlist, string $participant): ?Entry
    {
        /** @var ?Entry */
        return $this->entries($waitlist)
            ->andWhere('e.participant = :participant')
            ->andWhere('e.status IN (:live)')
            ->setParameter('participant', $participant)
            ->setParameter('live', [EntryStatus::Waiting->value, EntryStatus::Offered->value])
            ->setMaxResults(1)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }

    #[Override]
    public function waiting(Waitlist $waitlist, int $limit): array
    {
        return $this->ofWaitlist($waitlist, $limit, EntryStatus::Waiting);
    }

    #[Override]
    public function queued(Waitlist $waitlist, int $limit): array
    {
        /** @var list<Entry> */
        return $this->entries($waitlist)
            ->andWhere('e.status = :status')
            ->andWhere('e.rank LIKE :queued')
            ->setParameter('status', EntryStatus::Waiting->value)
            ->setParameter('queued', Entry::QUEUED.'%')
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    #[Override]
    public function ahead(Entry $entry): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Entry::class, 'e')
            ->where('e.waitlist = :waitlist')
            ->andWhere('e.status = :status')
            ->andWhere('e.rank < :rank')
            ->setParameter('waitlist', $entry->waitlist()->id)
            ->setParameter('status', EntryStatus::Waiting->value)
            ->setParameter('rank', $entry->rank())
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Override]
    public function ofWaitlist(Waitlist $waitlist, int $limit, ?EntryStatus $status = null): array
    {
        $query = $this->entries($waitlist)
            ->orderBy('e.rank', 'ASC')
            ->setMaxResults($limit);

        if ($status instanceof EntryStatus) {
            $query->andWhere('e.status = :status')->setParameter('status', $status->value);
        }

        /** @var list<Entry> */
        return $query->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
    }

    #[Override]
    public function places(Waitlist $waitlist, EntryStatus $status): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(e.places), 0)')
            ->from(Entry::class, 'e')
            ->where('e.waitlist = :waitlist')
            ->andWhere('e.status = :status')
            ->setParameter('waitlist', $waitlist->id)
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[Override]
    public function expired(Waitlist $waitlist, DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Entry> */
        return $this->entries($waitlist)
            ->andWhere('e.status = :status')
            ->andWhere('e.offerExpiresAt <= :now')
            ->orderBy('e.offerExpiresAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->setParameter('status', EntryStatus::Offered->value)
            ->setParameter('now', $now, self::MOMENT)
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    #[Override]
    public function waitlistsWithExpiredOffers(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<array{id: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(e.waitlist) AS id')
            ->from(Entry::class, 'e')
            ->where('e.status = :status')
            ->andWhere('e.offerExpiresAt <= :now')
            ->groupBy('e.waitlist')
            ->setParameter('status', EntryStatus::Offered->value)
            ->setParameter('now', $now, self::MOMENT)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['id'], $rows);
    }

    private function entries(Waitlist $waitlist): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Entry::class, 'e')
            ->where('e.waitlist = :waitlist')
            ->setParameter('waitlist', $waitlist->id);
    }
}
