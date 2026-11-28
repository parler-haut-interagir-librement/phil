<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;

/**
 * The snapshots in the table `phil_sites_console_snapshot`, whose identifiers sort in the order they were taken.
 */
final readonly class DoctrineSnapshotRepository implements SnapshotRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Snapshot $snapshot): void
    {
        $this->entityManager->persist($snapshot);
        $this->entityManager->flush();
    }

    #[Override]
    public function latest(string $siteId): ?Snapshot
    {
        return $this->entityManager->getRepository(Snapshot::class)->findOneBy(['siteId' => $siteId], ['id' => 'DESC']);
    }

    #[Override]
    public function history(string $siteId, int $limit): array
    {
        return $this->entityManager->getRepository(Snapshot::class)->findBy(['siteId' => $siteId], ['id' => 'DESC'], max(1, $limit));
    }

    #[Override]
    public function purgeBefore(DateTimeImmutable $moment): int
    {
        $removed = 0;

        foreach ($this->siteIds() as $siteId) {
            $latest = $this->latest($siteId);
            $result = $this->entityManager->createQueryBuilder()
                ->delete(Snapshot::class, 's')
                ->where('s.siteId = :site')
                ->andWhere('s.takenAt < :moment')
                ->andWhere('s.id <> :latest')
                ->setParameter('site', $siteId)
                ->setParameter('moment', $moment, 'datetime_immutable')
                ->setParameter('latest', $latest?->id() ?? '')
                ->getQuery()
                ->execute();
            $removed += \is_int($result) ? $result : 0;
        }

        return $removed;
    }

    #[Override]
    public function removeSite(string $siteId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(Snapshot::class, 's')
            ->where('s.siteId = :site')
            ->setParameter('site', $siteId)
            ->getQuery()
            ->execute();
    }

    /**
     * @return list<string>
     */
    private function siteIds(): array
    {
        /** @var list<array{siteId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT s.siteId')
            ->from(Snapshot::class, 's')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['siteId'], $rows);
    }
}
