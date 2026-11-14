<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\Status\NotificationState;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The notifications in their table, through DBAL: the unread count is one indexed `COUNT(*)` on the recipient and the
 * state, the centre one indexed range, and a notification collapses into its group with one update. Two notifications
 * of the same group recorded at the same instant may each find no unread one and both be kept: the next one collapses
 * into the latest.
 */
final readonly class DbalNotificationRepository implements NotificationRepository
{
    private const string TABLE = NotificationTables::NOTIFICATIONS;

    private const string INSTANT = Types::DATETIME_UTC_IMMUTABLE;

    /** The types of the instants a row is written with. */
    private const array INSTANTS = ['created_at' => self::INSTANT, 'updated_at' => self::INSTANT, 'read_at' => self::INSTANT, 'digested_at' => self::INSTANT];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(Notification $notification, bool $collapse): Notification
    {
        if ($collapse) {
            $kept = $this->hydrateFirst($this->select()
                ->where('recipient = :recipient', 'group_key = :group', 'state = :unread')
                ->setParameter('recipient', $notification->recipient)
                ->setParameter('group', $notification->groupKey())
                ->setParameter('unread', NotificationState::Unread->value)
                ->orderBy('updated_at', 'DESC'));

            if ($kept instanceof Notification) {
                $kept->collapse($notification);
                $this->connection->update(self::TABLE, [
                    'occurrences' => $kept->count,
                    'actor' => $kept->actor,
                    'data' => $this->json($kept->data),
                    'updated_at' => $kept->updatedAt,
                    'digested_at' => null,
                ], ['id' => $kept->id], self::INSTANTS);

                return $kept;
            }
        }

        $this->connection->insert(self::TABLE, [
            'id' => $notification->id,
            'recipient' => $notification->recipient,
            'type' => $notification->type,
            'actor' => $notification->actor,
            'subject_type' => $notification->subject?->type,
            'subject_id' => $notification->subject?->id,
            'data' => $this->json($notification->data),
            'origin' => $notification->origin,
            'group_key' => $notification->groupKey(),
            'state' => $notification->state->value,
            'occurrences' => $notification->count,
            'created_at' => $notification->createdAt,
            'updated_at' => $notification->updatedAt,
            'read_at' => $notification->readAt,
            'digested_at' => null,
        ], self::INSTANTS);

        return $notification;
    }

    #[Override]
    public function find(string $recipient, string $id): ?Notification
    {
        return $this->hydrateFirst($this->select()
            ->where('recipient = :recipient', 'id = :id')
            ->setParameter('recipient', $recipient)
            ->setParameter('id', $id));
    }

    #[Override]
    public function unreadCount(string $recipient): int
    {
        return new StoredRow(['n' => $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE recipient = ? AND state = ?', self::TABLE),
            [$recipient, NotificationState::Unread->value],
        )], 'count')->int('n');
    }

    #[Override]
    public function latest(string $recipient, int $limit): array
    {
        return $this->hydrate($this->select()
            ->where('recipient = :recipient', 'state IN (:states)')
            ->setParameter('recipient', $recipient)
            ->setParameter('states', [NotificationState::Unread->value, NotificationState::Read->value], ArrayParameterType::STRING)
            ->orderBy('updated_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit));
    }

    #[Override]
    public function markRead(string $recipient, string $id, DateTimeImmutable $at): bool
    {
        return 0 < $this->connection->update(
            self::TABLE,
            ['state' => NotificationState::Read->value, 'read_at' => $at],
            ['recipient' => $recipient, 'id' => $id, 'state' => NotificationState::Unread->value],
            self::INSTANTS,
        );
    }

    #[Override]
    public function markAllRead(string $recipient, DateTimeImmutable $at): int
    {
        return (int) $this->connection->update(
            self::TABLE,
            ['state' => NotificationState::Read->value, 'read_at' => $at],
            ['recipient' => $recipient, 'state' => NotificationState::Unread->value],
            self::INSTANTS,
        );
    }

    #[Override]
    public function archive(string $recipient, string $id): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET state = ? WHERE recipient = ? AND id = ? AND state <> ?', self::TABLE),
            [NotificationState::Archived->value, $recipient, $id, NotificationState::Archived->value],
        );
    }

    #[Override]
    public function undigested(string $recipient, DateTimeImmutable $since, int $limit): array
    {
        return $this->hydrate($this->awaiting($this->select(), $since)
            ->andWhere('recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('updated_at', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setMaxResults($limit));
    }

    #[Override]
    public function markDigested(string $recipient, array $ids, DateTimeImmutable $at): void
    {
        // An empty list is written IN (NULL), which matches nothing.
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET digested_at = ? WHERE recipient = ? AND id IN (?)', self::TABLE),
            [$at, $recipient, $ids],
            [self::INSTANT, ParameterType::STRING, ArrayParameterType::STRING],
        );
    }

    #[Override]
    public function awaitingDigest(DateTimeImmutable $since, ?string $after, int $limit): array
    {
        $query = $this->awaiting($this->connection->createQueryBuilder()->select('DISTINCT recipient')->from(self::TABLE), $since)
            ->orderBy('recipient', 'ASC')
            ->setMaxResults($limit);

        if (null !== $after) {
            $query->andWhere('recipient > :after')->setParameter('after', $after);
        }

        return array_map(static fn (array $row): string => new StoredRow($row, 'recipient')->string('recipient'), $query->executeQuery()->fetchAllAssociative());
    }

    #[Override]
    public function purge(DateTimeImmutable $readBefore, DateTimeImmutable $unreadBefore, int $limit): int
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::TABLE)
            ->where('(state = :unread AND updated_at < :unreadBefore) OR (state <> :unread AND updated_at < :readBefore)')
            ->setParameter('unread', NotificationState::Unread->value)
            ->setParameter('unreadBefore', $unreadBefore, self::INSTANT)
            ->setParameter('readBefore', $readBefore, self::INSTANT)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();

        // An empty list is written IN (NULL), which matches nothing.
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE id IN (?)', self::TABLE),
            [$ids],
            [ArrayParameterType::STRING],
        );
    }

    private function select(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->select('*')->from(self::TABLE);
    }

    private function awaiting(QueryBuilder $query, DateTimeImmutable $since): QueryBuilder
    {
        return $query
            ->where('state = :unread', 'digested_at IS NULL', 'updated_at >= :since')
            ->setParameter('unread', NotificationState::Unread->value)
            ->setParameter('since', $since, self::INSTANT);
    }

    private function hydrateFirst(QueryBuilder $query): ?Notification
    {
        return $this->hydrate($query)[0] ?? null;
    }

    /**
     * @return list<Notification>
     */
    private function hydrate(QueryBuilder $query): array
    {
        return array_map($this->notification(...), $query->executeQuery()->fetchAllAssociative());
    }

    /**
     * @param array<string, mixed> $columns
     *
     * @throws InvalidNotification when the row is not a notification
     */
    private function notification(array $columns): Notification
    {
        $row = new StoredRow($columns, 'notification');
        $platform = $this->connection->getDatabasePlatform();
        $subjectType = $row->nullableString('subject_type');
        $state = NotificationState::tryFrom($row->string('state'));

        if (null === $state) {
            throw InvalidNotification::because('The state of a stored notification is "unread", "read" or "archived".');
        }

        return Notification::restore(
            $row->string('id'),
            $row->string('type'),
            $row->string('recipient'),
            null === $subjectType ? null : new SubjectReference($subjectType, $row->string('subject_id')),
            $row->date('created_at', $platform),
            $row->nullableString('actor'),
            NotificationData::from($row->json('data')),
            $row->nullableString('origin'),
            $state,
            $row->int('occurrences'),
            $row->date('updated_at', $platform),
            $row->nullableDate('read_at', $platform),
        );
    }

    private function json(NotificationData $data): string
    {
        return json_encode((object) $data->values, \JSON_THROW_ON_ERROR);
    }
}
