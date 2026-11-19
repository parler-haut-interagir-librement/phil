<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\Exception\InvalidEvent;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;

/**
 * The groups and events in `phil_error_group` and `phil_error_event`, through DBAL. A group is updated, or inserted
 * when it has no row — the race of two first events ends in the update. An event is appended, then the events of its
 * group past the cap are deleted, the oldest first, in one statement. The instants are written and read in UTC
 * (`datetime_utc_immutable`), so that two servers in different time zones read the same moment.
 */
final readonly class DbalErrorStore implements ErrorStore, ErrorGroupReader
{
    public const int DEFAULT_EVENTS_PER_GROUP = 50;

    /**
     * The nesting of an event's plain values: the event, its exceptions, one exception, its frames, one frame, a value.
     */
    private const int PAYLOAD_DEPTH = 6;

    private const string COLUMNS = 'fingerprint, title, culprit, status, times_seen, first_seen_at, last_seen_at, last_release, resolved_release';

    private const array TYPES = ['first_seen_at' => Types::DATETIME_UTC_IMMUTABLE, 'last_seen_at' => Types::DATETIME_UTC_IMMUTABLE];

    public function __construct(
        private Connection $connection,
        private int $eventsPerGroup = self::DEFAULT_EVENTS_PER_GROUP,
    ) {
    }

    #[Override]
    public function find(string $fingerprint): ?ErrorGroup
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.ErrorTables::GROUP.' WHERE fingerprint = ?', [$fingerprint]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function save(ErrorGroup $group, ?ErrorEvent $event = null): void
    {
        $values = [
            'title' => $group->title,
            'culprit' => $group->culprit,
            'status' => $group->status->value,
            'times_seen' => $group->timesSeen,
            'first_seen_at' => $group->firstSeen,
            'last_seen_at' => $group->lastSeen,
            'last_release' => $group->lastRelease,
            'resolved_release' => $group->resolvedRelease,
        ];

        if (!$this->exists($group->fingerprint)) {
            try {
                $this->connection->insert(ErrorTables::GROUP, ['fingerprint' => $group->fingerprint] + $values, self::TYPES);
            } catch (UniqueConstraintViolationException) {
                $this->connection->update(ErrorTables::GROUP, $values, ['fingerprint' => $group->fingerprint], self::TYPES);
            }
        } else {
            $this->connection->update(ErrorTables::GROUP, $values, ['fingerprint' => $group->fingerprint], self::TYPES);
        }

        if ($event instanceof ErrorEvent) {
            $this->append($group->fingerprint, $event);
        }
    }

    #[Override]
    public function groups(?GroupStatus $status, int $limit, int $offset = 0): array
    {
        $sql = 'SELECT '.self::COLUMNS.' FROM '.ErrorTables::GROUP.($status instanceof GroupStatus ? ' WHERE status = ?' : '').' ORDER BY last_seen_at DESC, fingerprint ASC';
        $rows = $this->connection->fetchAllAssociative($this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit, $offset), $status instanceof GroupStatus ? [$status->value] : []);

        return array_map($this->hydrate(...), $rows);
    }

    #[Override]
    public function countGroups(?GroupStatus $status): int
    {
        return $this->integer($this->connection->fetchOne('SELECT COUNT(*) FROM '.ErrorTables::GROUP.($status instanceof GroupStatus ? ' WHERE status = ?' : ''), $status instanceof GroupStatus ? [$status->value] : []));
    }

    #[Override]
    public function events(string $fingerprint, int $limit): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery('SELECT payload FROM '.ErrorTables::EVENT.' WHERE fingerprint = ? ORDER BY id DESC', $limit);
        $events = [];

        foreach ($this->connection->fetchFirstColumn($sql, [$fingerprint]) as $payload) {
            try {
                $data = json_decode(\is_string($payload) ? $payload : '', true, self::PAYLOAD_DEPTH, \JSON_THROW_ON_ERROR);
                $events[] = ErrorEvent::fromArray(\is_array($data) ? $data : []);
            } catch (JsonException|InvalidEvent) {
                continue;
            }
        }

        return $events;
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $events = $this->connection->executeStatement('DELETE FROM '.ErrorTables::EVENT.' WHERE occurred_at < ?', [$before], [Types::DATETIME_UTC_IMMUTABLE]);
        $groups = $this->connection->executeStatement('DELETE FROM '.ErrorTables::GROUP.' WHERE last_seen_at < ?', [$before], [Types::DATETIME_UTC_IMMUTABLE]);

        return (int) $events + (int) $groups;
    }

    private function exists(string $fingerprint): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM '.ErrorTables::GROUP.' WHERE fingerprint = ?', [$fingerprint]);
    }

    private function append(string $fingerprint, ErrorEvent $event): void
    {
        $this->connection->insert(ErrorTables::EVENT, [
            'event_id' => $event->id,
            'fingerprint' => $fingerprint,
            'occurred_at' => $event->occurredAt,
            'payload' => json_encode($event->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION),
        ], ['occurred_at' => Types::DATETIME_UTC_IMMUTABLE]);

        $cut = $this->connection->fetchOne(
            $this->connection->getDatabasePlatform()->modifyLimitQuery('SELECT id FROM '.ErrorTables::EVENT.' WHERE fingerprint = ? ORDER BY id DESC', 1, $this->eventsPerGroup),
            [$fingerprint],
        );

        if (false !== $cut) {
            $this->connection->executeStatement('DELETE FROM '.ErrorTables::EVENT.' WHERE fingerprint = ? AND id <= ?', [$fingerprint, $cut]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ErrorGroup
    {
        return new ErrorGroup(
            $this->text($row['fingerprint']) ?? '',
            $this->text($row['title']) ?? '',
            $this->text($row['culprit']),
            GroupStatus::tryFrom($this->text($row['status']) ?? '') ?? GroupStatus::Open,
            $this->integer($row['times_seen']),
            $this->instant($row['first_seen_at']),
            $this->instant($row['last_seen_at']),
            $this->text($row['last_release']),
            $this->text($row['resolved_release']),
        );
    }

    /**
     * An instant read back as it was written, in UTC, whatever the time zone of PHP and of the database.
     */
    private function instant(mixed $value): DateTimeImmutable
    {
        $instant = $this->connection->convertToPHPValue($value, Types::DATETIME_UTC_IMMUTABLE);
        \assert($instant instanceof DateTimeImmutable);

        return $instant;
    }

    /**
     * A count read back: an integer from SQLite and PostgreSQL, a numeric text from MySQL.
     */
    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function text(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
