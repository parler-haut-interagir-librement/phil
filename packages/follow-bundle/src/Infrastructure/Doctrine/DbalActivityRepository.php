<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Model\Activity;

/**
 * The activities in their table, through DBAL, the mentions a JSON list. An erased member's mentions are found with
 * `LIKE` on their quoted identifier, then taken out in PHP, a page at a time.
 */
final readonly class DbalActivityRepository implements ActivityRepository
{
    private const string TABLE = FollowTables::ACTIVITIES;

    private const int PAGE = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(Activity $activity): bool
    {
        if (false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE id = ?', self::TABLE), [$activity->id])) {
            return false;
        }

        Sql::insertIgnoring($this->connection, self::TABLE, ['id', 'subject_type', 'subject_id', 'event', 'actor', 'mentions', 'occurred_at'], [[
            $activity->id,
            $activity->subject->type,
            $activity->subject->id,
            $activity->event,
            $activity->actor,
            Sql::json($activity->mentions),
            $activity->occurredAt,
        ]], [6 => Types::DATETIME_UTC_IMMUTABLE]);

        return true;
    }

    #[Override]
    public function find(string $id): ?Activity
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s WHERE id = ?', self::TABLE), [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function purgeBefore(DateTimeImmutable $before, int $limit): int
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::TABLE)
            ->where('occurred_at < :before')
            ->setParameter('before', $before, Types::DATETIME_UTC_IMMUTABLE)
            ->orderBy('occurred_at')
            ->setMaxResults($limit)
            ->fetchFirstColumn();

        if ([] === $ids) {
            return 0;
        }

        return (int) $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE id IN (?)', self::TABLE), [$ids], [ArrayParameterType::STRING]);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->connection->delete(self::TABLE, ['actor' => $member]);
        // An identifier holds letters, digits, "_", ".", ":" and "-": quoted, it is written as it is in the JSON list.
        $pattern = '%"'.str_replace(['!', '_', '%'], ['!!', '!_', '!%'], $member).'"%';
        $after = '';

        do {
            $rows = $this->connection->createQueryBuilder()
                ->select('id', 'mentions')
                ->from(self::TABLE)
                ->where('id > :after', "mentions LIKE :pattern ESCAPE '!'")
                ->setParameter('after', $after)
                ->setParameter('pattern', $pattern)
                ->orderBy('id')
                ->setMaxResults(self::PAGE)
                ->fetchAllAssociative();

            foreach ($rows as $values) {
                $row = new Row($values, $this->connection);
                $after = $row->string('id');
                $mentions = array_values(array_filter($row->json('mentions'), static fn (mixed $mentioned): bool => $mentioned !== $member));
                $this->connection->update(self::TABLE, ['mentions' => Sql::json($mentions)], ['id' => $after]);
            }
        } while (self::PAGE === \count($rows));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws InvalidFollow when the row is no activity
     */
    private function hydrate(array $values): Activity
    {
        $row = new Row($values, $this->connection);

        return new Activity(
            $row->string('id'),
            Identifier::subject($row->string('subject_type'), $row->string('subject_id')),
            $row->string('event'),
            $row->nullableString('actor'),
            array_values(array_filter($row->json('mentions'), \is_string(...))),
            $row->instant('occurred_at'),
        );
    }
}
