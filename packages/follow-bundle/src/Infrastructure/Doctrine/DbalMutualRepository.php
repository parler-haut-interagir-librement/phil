<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;

/**
 * The mutual relationships in their table, one row per pair in byte order, read from either side through the primary
 * key and the index on the second member.
 */
final readonly class DbalMutualRepository implements MutualRepository
{
    private const string TABLE = FollowTables::MUTUALS;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(MemberPair $pair, DateTimeImmutable $since): bool
    {
        if ($this->exists($pair)) {
            return false;
        }

        Sql::insertIgnoring($this->connection, self::TABLE, ['first_member', 'second_member', 'since'], [[$pair->first, $pair->second, $since]], [2 => Types::DATETIME_UTC_IMMUTABLE]);

        return true;
    }

    #[Override]
    public function remove(MemberPair $pair): bool
    {
        return 0 < $this->connection->delete(self::TABLE, ['first_member' => $pair->first, 'second_member' => $pair->second]);
    }

    #[Override]
    public function exists(MemberPair $pair): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE first_member = ? AND second_member = ?', self::TABLE), [$pair->first, $pair->second]);
    }

    #[Override]
    public function of(string $member, ?string $after, int $limit): array
    {
        $others = [
            ...$this->side('second_member', 'first_member', $member, $after, $limit),
            ...$this->side('first_member', 'second_member', $member, $after, $limit),
        ];
        sort($others, \SORT_STRING);

        return \array_slice($others, 0, $limit);
    }

    #[Override]
    public function exportOf(string $member): array
    {
        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT * FROM %s WHERE first_member = ? OR second_member = ? ORDER BY since', self::TABLE), [$member, $member]);

        return array_map(function (array $values) use ($member): array {
            $row = new Row($values, $this->connection);
            $first = $row->string('first_member');

            return ['member' => $first === $member ? $row->string('second_member') : $first, 'since' => $row->instant('since')];
        }, $rows);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->connection->delete(self::TABLE, ['first_member' => $member]);
        $this->connection->delete(self::TABLE, ['second_member' => $member]);
    }

    /**
     * @return list<string>
     */
    private function side(string $other, string $own, string $member, ?string $after, int $limit): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select($other)
            ->from(self::TABLE)
            ->where($own.' = :member')
            ->setParameter('member', $member)
            ->orderBy($other)
            ->setMaxResults($limit);

        if (null !== $after) {
            $query->andWhere($other.' > :after')->setParameter('after', $after);
        }

        return Row::strings($query->fetchFirstColumn());
    }
}
