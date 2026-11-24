<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The follows in their table, through DBAL: a member's follow is read by its primary key, the followers of a subject by
 * one indexed range after a cursor, and a page of a delivery in two queries — the page's members, then their follows of
 * the chain —, one when the chain is the subject alone.
 */
final readonly class DbalFollowRepository implements FollowRepository
{
    private const string TABLE = FollowTables::FOLLOWS;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function find(string $member, SubjectReference $subject): ?Follow
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM %s WHERE member = ? AND subject_type = ? AND subject_id = ?', self::TABLE),
            [$member, $subject->type, $subject->id],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function save(Follow $follow): void
    {
        $key = ['member' => $follow->member, 'subject_type' => $follow->subject->type, 'subject_id' => $follow->subject->id];
        $values = ['level' => $follow->level()->value, 'events' => Sql::json($follow->events()->toArray())];

        if (false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE member = ? AND subject_type = ? AND subject_id = ?', self::TABLE), array_values($key))) {
            $this->connection->update(self::TABLE, $values, $key);

            return;
        }

        $this->connection->insert(self::TABLE, [...$key, ...$values, 'followed_at' => $follow->followedAt], ['followed_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function remove(string $member, SubjectReference $subject): bool
    {
        return 0 < $this->connection->delete(self::TABLE, ['member' => $member, 'subject_type' => $subject->type, 'subject_id' => $subject->id]);
    }

    #[Override]
    public function followersOf(SubjectReference $subject, ?string $after, int $limit): array
    {
        $query = $this->following($subject)->select('*')->orderBy('member')->setMaxResults($limit);

        if (null !== $after) {
            $query->andWhere('member > :after')->setParameter('after', $after);
        }

        return $this->hydrateAll($query->fetchAllAssociative());
    }

    #[Override]
    public function countFollowers(SubjectReference $subject): int
    {
        $count = $this->following($subject)->select('COUNT(*)')->fetchOne();

        return is_numeric($count) ? (int) $count : 0;
    }

    #[Override]
    public function ofMember(string $member, ?string $after, int $limit): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->where('member = :member')
            ->setParameter('member', $member)
            ->orderBy('subject_type')
            ->addOrderBy('subject_id')
            ->setMaxResults($limit);

        if (null !== $after) {
            $cursor = Identifier::parseSubject($after);
            $query->andWhere('(subject_type > :type OR (subject_type = :type AND subject_id > :id))')
                ->setParameter('type', $cursor->type)
                ->setParameter('id', $cursor->id);
        }

        return $this->hydrateAll($query->fetchAllAssociative());
    }

    #[Override]
    public function candidates(array $chain, ?string $after, int $limit): array
    {
        if ([] === $chain) {
            return [];
        }

        $page = $this->onChain($chain)->orderBy('member')->setMaxResults($limit);

        if (null !== $after) {
            $page->andWhere('member > :after')->setParameter('after', $after);
        }

        if (1 === \count($chain)) {
            return $this->hydrateAll($page->select('*')->fetchAllAssociative());
        }

        $members = Row::strings($page->select('DISTINCT member')->fetchFirstColumn());

        if ([] === $members) {
            return [];
        }

        return $this->hydrateAll($this->onChain($chain)
            ->select('*')
            ->andWhere('member IN (:members)')
            ->setParameter('members', $members, ArrayParameterType::STRING)
            ->orderBy('member')
            ->fetchAllAssociative());
    }

    #[Override]
    public function eraseMember(string $member, SubjectReference $memberSubject): void
    {
        $this->connection->delete(self::TABLE, ['member' => $member]);
        $this->connection->delete(self::TABLE, ['subject_type' => $memberSubject->type, 'subject_id' => $memberSubject->id]);
    }

    private function following(SubjectReference $subject): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from(self::TABLE)
            ->where('subject_type = :type', 'subject_id = :id', 'level <> :ignoring')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->setParameter('ignoring', FollowLevel::Ignoring->value);
    }

    /**
     * @param non-empty-list<SubjectReference> $chain
     */
    private function onChain(array $chain): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()->from(self::TABLE);
        $any = [];

        foreach ($chain as $index => $subject) {
            $any[] = \sprintf('(subject_type = :type%1$d AND subject_id = :id%1$d)', $index);
            $query->setParameter('type'.$index, $subject->type)->setParameter('id'.$index, $subject->id);
        }

        return $query->where('('.implode(' OR ', $any).')');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<Follow>
     */
    private function hydrateAll(array $rows): array
    {
        return array_values(array_map($this->hydrate(...), $rows));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws InvalidFollow when the row is no follow
     */
    private function hydrate(array $values): Follow
    {
        $row = new Row($values, $this->connection);

        return new Follow(
            $row->string('member'),
            Identifier::subject($row->string('subject_type'), $row->string('subject_id')),
            FollowLevel::tryFrom($row->string('level')) ?? throw InvalidFollow::because('The level of a stored follow is "watching", "mentions", "ignoring" or "custom".'),
            EventPreferences::fromArray($row->json('events')),
            $row->instant('followed_at'),
        );
    }
}
