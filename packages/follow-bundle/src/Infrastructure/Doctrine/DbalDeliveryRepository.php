<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Uid\Uuid;

/**
 * The claims of deliveries and the marks of notifications, through DBAL. A page is claimed in two statements, whatever
 * its size: one `INSERT` that skips the members already claimed, then one read of the rows that carry this attempt's
 * token — the members no earlier attempt and no other worker holds.
 */
final readonly class DbalDeliveryRepository implements DeliveryRepository
{
    private const string CLAIMS = FollowTables::DELIVERIES;

    private const string MARKS = FollowTables::UNSEEN;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function claim(string $activity, array $members, DateTimeImmutable $at): array
    {
        if ([] === $members) {
            return [];
        }

        $token = Uuid::v4()->toRfc4122();
        Sql::insertIgnoring(
            $this->connection,
            self::CLAIMS,
            ['activity_id', 'member', 'claim', 'claimed_at'],
            array_map(static fn (string $member): array => [$activity, $member, $token, $at], $members),
            [3 => Types::DATETIME_UTC_IMMUTABLE],
        );
        $claimed = array_flip(Row::strings($this->connection->fetchFirstColumn(
            \sprintf('SELECT member FROM %s WHERE activity_id = ? AND claim = ? AND member IN (?)', self::CLAIMS),
            [$activity, $token, $members],
            [Types::STRING, Types::STRING, ArrayParameterType::STRING],
        )));

        return array_values(array_filter($members, static fn (string $member): bool => isset($claimed[$member])));
    }

    #[Override]
    public function release(string $activity, array $members): void
    {
        if ([] !== $members) {
            $this->connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE activity_id = ? AND member IN (?)', self::CLAIMS),
                [$activity, $members],
                [Types::STRING, ArrayParameterType::STRING],
            );
        }
    }

    #[Override]
    public function unseen(SubjectReference $subject, array $members, DateTimeImmutable $since): array
    {
        if ([] === $members) {
            return [];
        }

        $unseen = array_flip(Row::strings($this->connection->fetchFirstColumn(
            \sprintf('SELECT member FROM %s WHERE subject_type = ? AND subject_id = ? AND member IN (?) AND notified_at >= ?', self::MARKS),
            [$subject->type, $subject->id, $members, $since],
            [Types::STRING, Types::STRING, ArrayParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
        )));

        return array_values(array_filter($members, static fn (string $member): bool => isset($unseen[$member])));
    }

    #[Override]
    public function markNotified(SubjectReference $subject, array $members, DateTimeImmutable $at): void
    {
        if ([] === $members) {
            return;
        }

        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE subject_type = ? AND subject_id = ? AND member IN (?)', self::MARKS),
            [$subject->type, $subject->id, $members],
            [Types::STRING, Types::STRING, ArrayParameterType::STRING],
        );
        Sql::insertIgnoring(
            $this->connection,
            self::MARKS,
            ['member', 'subject_type', 'subject_id', 'notified_at'],
            array_map(static fn (string $member): array => [$member, $subject->type, $subject->id, $at], $members),
            [3 => Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function seen(string $member, SubjectReference $subject): void
    {
        $this->connection->delete(self::MARKS, ['member' => $member, 'subject_type' => $subject->type, 'subject_id' => $subject->id]);
    }

    #[Override]
    public function purgeClaimsBefore(DateTimeImmutable $before, int $limit): int
    {
        return $this->purge(self::CLAIMS, 'activity_id', 'claimed_at', $before, $limit);
    }

    #[Override]
    public function purgeMarksBefore(DateTimeImmutable $before, int $limit): int
    {
        return $this->purge(self::MARKS, 'member', 'notified_at', $before, $limit);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->connection->delete(self::CLAIMS, ['member' => $member]);
        $this->connection->delete(self::MARKS, ['member' => $member]);
    }

    /**
     * Deletes the rows older than the instant of the first `$limit` values of a column, so that one statement stays
     * bounded on every platform, `DELETE … LIMIT` being MySQL's alone.
     */
    private function purge(string $table, string $column, string $instant, DateTimeImmutable $before, int $limit): int
    {
        $values = $this->connection->createQueryBuilder()
            ->select('DISTINCT '.$column)
            ->from($table)
            ->where($instant.' < :before')
            ->setParameter('before', $before, Types::DATETIME_UTC_IMMUTABLE)
            ->orderBy($column)
            ->setMaxResults($limit)
            ->fetchFirstColumn();

        if ([] === $values) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE %s IN (?) AND %s < ?', $table, $column, $instant),
            [$values, $before],
            [ArrayParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );
    }
}
