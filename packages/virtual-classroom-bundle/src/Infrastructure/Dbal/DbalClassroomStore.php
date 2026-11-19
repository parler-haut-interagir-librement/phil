<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Domain\Status\RoomStatus;

/**
 * The rooms, stays and recordings in the tables of a Doctrine DBAL connection ({@see ClassroomTables}). A row that
 * cannot be read back — an identifier out of its pattern, a status unknown — is left out rather than thrown on.
 */
final readonly class DbalClassroomStore implements ClassroomStore
{
    private const string ROOM_COLUMNS = 'id, provider, title, record, max_minutes, owner, status, session_key, created_at, opened_at, ended_at';

    /** @var non-empty-string */
    private string $rooms;

    /** @var non-empty-string */
    private string $stays;

    /** @var non-empty-string */
    private string $recordings;

    /**
     * @param non-empty-string $prefix
     */
    public function __construct(
        private Connection $connection,
        private string $prefix = ClassroomTables::PREFIX,
    ) {
        $this->rooms = ClassroomTables::rooms($prefix);
        $this->stays = ClassroomTables::stays($prefix);
        $this->recordings = ClassroomTables::recordings($prefix);
    }

    /**
     * Adds the tables to a schema that lacks them.
     */
    public function configureSchema(Schema $schema): Schema
    {
        return ClassroomTables::for($this->connection->getDatabasePlatform(), $this->prefix)->configure($schema);
    }

    #[Override]
    public function saveRoom(Room $room): void
    {
        $row = [
            'provider' => $room->provider,
            'title' => $room->title,
            'record' => $room->record,
            'max_minutes' => $room->maxMinutes,
            'owner' => $room->owner,
            'status' => $room->status->value,
            'session_key' => $room->sessionKey,
            'created_at' => $room->createdAt->getTimestamp(),
            'opened_at' => $room->openedAt?->getTimestamp(),
            'ended_at' => $room->endedAt?->getTimestamp(),
        ];
        $types = ['record' => ParameterType::BOOLEAN];

        if (0 === $this->connection->update($this->rooms, $row, ['id' => $room->id->value], $types)) {
            try {
                $this->connection->insert($this->rooms, ['id' => $room->id->value, ...$row], $types);
            } catch (UniqueConstraintViolationException) {
                // Saved meanwhile, or saved unchanged: a MySQL update that changes nothing counts no row.
                $this->connection->update($this->rooms, $row, ['id' => $room->id->value], $types);
            }
        }
    }

    #[Override]
    public function room(RoomId $id): ?Room
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::ROOM_COLUMNS.' FROM '.$this->rooms.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->toRoom($row);
    }

    #[Override]
    public function roomOfOwner(string $owner): ?Room
    {
        $row = $this->connection->fetchAssociative(
            $this->connection->getDatabasePlatform()->modifyLimitQuery('SELECT '.self::ROOM_COLUMNS.' FROM '.$this->rooms.' WHERE owner = ? ORDER BY created_at DESC, id DESC', 1),
            [$owner],
        );

        return false === $row ? null : $this->toRoom($row);
    }

    #[Override]
    public function openRooms(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            $this->connection->getDatabasePlatform()->modifyLimitQuery('SELECT '.self::ROOM_COLUMNS.' FROM '.$this->rooms.' WHERE status = ? ORDER BY created_at, id', $limit),
            [RoomStatus::Open->value],
        );

        return array_values(array_filter(array_map($this->toRoom(...), $rows)));
    }

    #[Override]
    public function arrive(RoomId $room, ParticipantId $participant, DateTimeImmutable $at): bool
    {
        $known = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.$this->stays.' WHERE room_id = ? AND participant_id = ? AND (left_at IS NULL OR (joined_at <= ? AND left_at >= ?))',
            [$room->value, $participant->value, $at->getTimestamp(), $at->getTimestamp()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        if (!is_numeric($known) || 0 !== (int) $known) {
            return false;
        }

        try {
            $this->connection->insert($this->stays, ['room_id' => $room->value, 'participant_id' => $participant->value, 'joined_at' => $at->getTimestamp(), 'left_at' => null]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function leave(RoomId $room, ParticipantId $participant, DateTimeImmutable $at): bool
    {
        $joined = $this->connection->fetchOne(
            'SELECT MAX(joined_at) FROM '.$this->stays.' WHERE room_id = ? AND participant_id = ? AND left_at IS NULL AND joined_at <= ?',
            [$room->value, $participant->value, $at->getTimestamp()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );

        if (!is_numeric($joined)) {
            return false;
        }

        return 1 === (int) $this->connection->executeStatement(
            'UPDATE '.$this->stays.' SET left_at = ? WHERE room_id = ? AND participant_id = ? AND joined_at = ? AND left_at IS NULL',
            [$at->getTimestamp(), $room->value, $participant->value, (int) $joined],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function leaveAll(RoomId $room, DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE '.$this->stays.' SET left_at = ? WHERE room_id = ? AND left_at IS NULL',
            [$at->getTimestamp(), $room->value],
            [ParameterType::INTEGER, ParameterType::STRING],
        );
    }

    #[Override]
    public function present(RoomId $room): array
    {
        $values = $this->connection->fetchFirstColumn('SELECT DISTINCT participant_id FROM '.$this->stays.' WHERE room_id = ? AND left_at IS NULL ORDER BY participant_id', [$room->value]);

        return array_values(array_filter(array_map(ParticipantId::tryFrom(...), $values)));
    }

    #[Override]
    public function stays(RoomId $room): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT participant_id, joined_at, left_at FROM '.$this->stays.' WHERE room_id = ? ORDER BY participant_id, joined_at', [$room->value]);
        $stays = [];

        foreach ($rows as $row) {
            $participant = ParticipantId::tryFrom($row['participant_id'] ?? null);
            $interval = $this->toInterval($row);

            if ($participant instanceof ParticipantId && $interval instanceof Interval) {
                $stays['p'.$participant->value]['participant'] = $participant;
                $stays['p'.$participant->value]['stays'][] = $interval;
            }
        }

        return array_values($stays);
    }

    #[Override]
    public function staysOf(ParticipantId $participant): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT room_id, joined_at, left_at FROM '.$this->stays.' WHERE participant_id = ? ORDER BY joined_at, room_id', [$participant->value]);
        $stays = [];

        foreach ($rows as $row) {
            $interval = $this->toInterval($row);

            if (\is_string($row['room_id'] ?? null) && $interval instanceof Interval) {
                $stays[] = ['room' => $row['room_id'], 'interval' => $interval];
            }
        }

        return $stays;
    }

    #[Override]
    public function forget(ParticipantId $participant): int
    {
        return (int) $this->connection->delete($this->stays, ['participant_id' => $participant->value]);
    }

    #[Override]
    public function saveRecording(Recording $recording): bool
    {
        $key = ['room_id' => $recording->room->value, 'recording_id' => $recording->id, 'format' => $recording->format];
        $row = [
            'url' => $recording->url,
            'published' => $recording->published,
            'started_at' => $recording->startedAt?->getTimestamp(),
            'ended_at' => $recording->endedAt?->getTimestamp(),
            'expires_at' => $recording->expiresAt?->getTimestamp(),
        ];
        $types = ['published' => ParameterType::BOOLEAN];
        $known = $this->connection->fetchOne('SELECT COUNT(*) FROM '.$this->recordings.' WHERE room_id = ? AND recording_id = ? AND format = ?', array_values($key));

        if (!is_numeric($known) || 0 !== (int) $known) {
            $this->connection->update($this->recordings, $row, $key, $types);

            return false;
        }

        try {
            $this->connection->insert($this->recordings, [...$key, ...$row], $types);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function recordings(RoomId $room): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT recording_id, format, url, published, started_at, ended_at, expires_at FROM '.$this->recordings.' WHERE room_id = ? ORDER BY started_at, recording_id, format', [$room->value]);
        $recordings = [];

        foreach ($rows as $row) {
            try {
                $recordings[] = new Recording(
                    $this->text($row['recording_id'] ?? null),
                    $room,
                    $this->text($row['format'] ?? null),
                    \is_string($row['url'] ?? null) ? $row['url'] : null,
                    (bool) ($row['published'] ?? false),
                    $this->moment($row['started_at'] ?? null),
                    $this->moment($row['ended_at'] ?? null),
                    $this->moment($row['expires_at'] ?? null),
                );
            } catch (InvalidValue) {
                continue;
            }
        }

        return $recordings;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toRoom(array $row): ?Room
    {
        $status = RoomStatus::tryFrom($this->text($row['status'] ?? null));
        $created = $this->moment($row['created_at'] ?? null);

        if (null === $status || !$created instanceof DateTimeImmutable) {
            return null;
        }

        try {
            return Room::restore(
                RoomId::fromString($this->text($row['id'] ?? null)),
                $this->text($row['provider'] ?? null),
                $this->text($row['title'] ?? null),
                (bool) ($row['record'] ?? false),
                is_numeric($row['max_minutes'] ?? null) ? (int) $row['max_minutes'] : null,
                \is_string($row['owner'] ?? null) ? $row['owner'] : null,
                $created,
                $status,
                \is_string($row['session_key'] ?? null) ? $row['session_key'] : null,
                $this->moment($row['opened_at'] ?? null),
                $this->moment($row['ended_at'] ?? null),
            );
        } catch (InvalidValue|InvalidRoom) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toInterval(array $row): ?Interval
    {
        $joined = $this->moment($row['joined_at'] ?? null);

        return $joined instanceof DateTimeImmutable ? new Interval($joined, $this->moment($row['left_at'] ?? null)) : null;
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private function moment(mixed $value): ?DateTimeImmutable
    {
        return is_numeric($value) ? new DateTimeImmutable('@'.(int) $value) : null;
    }
}
