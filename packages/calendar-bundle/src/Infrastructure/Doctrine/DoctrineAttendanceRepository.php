<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Attendances with Doctrine. Counts and lists of attendees are read with DBAL, a page at a time, never as entities: an
 * event of thousands loads only the names it shows.
 */
final readonly class DoctrineAttendanceRepository implements AttendanceRepository
{
    /** What a member attends, read at most. */
    private const int ATTENDED = 10000;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Attendance $attendance): void
    {
        $this->entityManager->persist($attendance);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Attendance $attendance): void
    {
        $this->entityManager->remove($attendance);
        $this->entityManager->flush();
    }

    #[Override]
    public function of(Event $event, string $occurrence, Member $member): ?Attendance
    {
        return $this->entityManager->getRepository(Attendance::class)->findOneBy(['event' => $event, 'occurrence' => $occurrence, 'member' => $member->toString()]);
    }

    #[Override]
    public function count(Event $event, string $occurrence): int
    {
        $count = $this->entityManager->getConnection()->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE event = ? AND occurrence IN (?, ?)', Attendance::TABLE),
            [$event->id, $occurrence, Attendance::EVERY_OCCURRENCE],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    #[Override]
    public function attendees(Event $event, string $occurrence, int $limit, int $offset = 0): array
    {
        $query = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('member')
            ->from(Attendance::TABLE)
            ->where('event = :event')
            ->andWhere('occurrence IN (:occurrence, :every)')
            ->orderBy('created_at')
            ->addOrderBy('id')
            ->setParameter('event', $event->id)
            ->setParameter('occurrence', $occurrence)
            ->setParameter('every', Attendance::EVERY_OCCURRENCE)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return array_values(array_map(Member::fromString(...), array_filter($query->executeQuery()->fetchFirstColumn(), is_string(...))));
    }

    #[Override]
    public function attendedBy(Member $member): array
    {
        $rows = $this->entityManager->getConnection()->createQueryBuilder()
            ->select('event', 'occurrence')
            ->from(Attendance::TABLE)
            ->where('member = :member')
            ->orderBy('event')
            ->addOrderBy('occurrence')
            ->setParameter('member', $member->toString(), ParameterType::STRING)
            ->setMaxResults(self::ATTENDED)
            ->executeQuery()
            ->fetchAllAssociative();
        $attended = [];

        foreach ($rows as ['event' => $event, 'occurrence' => $occurrence]) {
            if (\is_string($event) && \is_string($occurrence)) {
                $attended[$event][] = $occurrence;
            }
        }

        return $attended;
    }

    #[Override]
    public function forgetMember(Member $member): void
    {
        $this->entityManager->getConnection()->delete(Attendance::TABLE, ['member' => $member->toString()]);
    }
}
