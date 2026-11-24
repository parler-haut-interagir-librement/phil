<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The requests in their table, through DBAL. Their identifiers are UUID v7, which sort as they were made: the pages of
 * an approver's requests are one indexed range after the last identifier read.
 */
final readonly class DbalFollowRequestRepository implements FollowRequestRepository
{
    private const string TABLE = FollowTables::REQUESTS;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(FollowRequest $request): bool
    {
        Sql::insertIgnoring($this->connection, self::TABLE, ['id', 'requester', 'subject_type', 'subject_id', 'kind', 'approver', 'level', 'events', 'requested_at'], [[
            $request->id,
            $request->requester,
            $request->subject->type,
            $request->subject->id,
            $request->kind->value,
            $request->approver,
            $request->level->value,
            Sql::json($request->events->toArray()),
            $request->requestedAt,
        ]], [8 => Types::DATETIME_UTC_IMMUTABLE]);

        return $this->find($request->id) instanceof FollowRequest;
    }

    #[Override]
    public function find(string $id): ?FollowRequest
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s WHERE id = ?', self::TABLE), [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function findPending(string $requester, SubjectReference $subject, RequestKind $kind): ?FollowRequest
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM %s WHERE requester = ? AND subject_type = ? AND subject_id = ? AND kind = ?', self::TABLE),
            [$requester, $subject->type, $subject->id, $kind->value],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function pendingFor(string $approver, ?string $after, int $limit): array
    {
        return $this->page('approver', $approver, $after, $limit);
    }

    #[Override]
    public function sentBy(string $requester, ?string $after, int $limit): array
    {
        return $this->page('requester', $requester, $after, $limit);
    }

    #[Override]
    public function remove(string $id): bool
    {
        return 0 < $this->connection->delete(self::TABLE, ['id' => $id]);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->connection->delete(self::TABLE, ['requester' => $member]);
        $this->connection->delete(self::TABLE, ['approver' => $member]);
    }

    /**
     * @return list<FollowRequest>
     */
    private function page(string $column, string $member, ?string $after, int $limit): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::TABLE)
            ->where($column.' = :member')
            ->setParameter('member', $member)
            ->orderBy('id')
            ->setMaxResults($limit);

        if (null !== $after) {
            $query->andWhere('id > :after')->setParameter('after', $after);
        }

        return array_values(array_map($this->hydrate(...), $query->fetchAllAssociative()));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws InvalidFollow when the row is no request
     */
    private function hydrate(array $values): FollowRequest
    {
        $row = new Row($values, $this->connection);

        return new FollowRequest(
            $row->string('id'),
            $row->string('requester'),
            Identifier::subject($row->string('subject_type'), $row->string('subject_id')),
            RequestKind::tryFrom($row->string('kind')) ?? throw InvalidFollow::because('The kind of a stored request is "follow" or "mutual".'),
            $row->string('approver'),
            FollowLevel::tryFrom($row->string('level')) ?? throw InvalidFollow::because('The level of a stored request is "watching", "mentions", "ignoring" or "custom".'),
            EventPreferences::fromArray($row->json('events')),
            $row->instant('requested_at'),
        );
    }
}
