<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Override;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * The polls and their slots in the tables `phil_scheduling_poll` and `phil_scheduling_poll_slot`. A poll is read again
 * from its table each time, so that a closing written by another process is seen; the closing is one `UPDATE` whose
 * condition is that the poll is not closed yet.
 */
final readonly class DoctrineSchedulingPollRepository implements SchedulingPollRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function add(SchedulingPoll $poll, array $slots): void
    {
        $this->entityManager->persist($poll);

        foreach ($slots as $slot) {
            $this->entityManager->persist($slot);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function get(string $id): ?SchedulingPoll
    {
        $poll = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(SchedulingPoll::class, 'p')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $poll instanceof SchedulingPoll ? $poll : null;
    }

    #[Override]
    public function slots(string $poll): array
    {
        /** @var list<Slot> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Slot::class, 's')
            ->where('s.poll = :poll')
            ->orderBy('s.position', 'ASC')
            ->setParameter('poll', $poll)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function close(string $id, DateTimeImmutable $at, ClosedBy $by, ?string $slot): bool
    {
        $closed = $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.SchedulingPoll::TABLE.' SET closed_at = ?, closed_by = ?, chosen_slot = ? WHERE id = ? AND closed_at IS NULL',
            [$at, $by->value, $slot, $id],
            [Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );

        return 1 === $closed;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<array{id: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id')
            ->from(SchedulingPoll::class, 'p')
            ->where('p.closedAt IS NULL')
            ->andWhere('p.deadline <= :now')
            ->orderBy('p.deadline', 'ASC')
            ->setParameter('now', $now, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'id');
    }
}
