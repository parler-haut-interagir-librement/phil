<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;

/**
 * The check-ins in one table, by plain queries on a Doctrine connection. The scan identifier is the primary key and
 * the credential with the sequence a unique key, so that a scan sent twice, or two admissions decided on the same
 * attendance, are refused by the database itself. Instants are Unix timestamps; the scanned code is never stored.
 *
 * The attendance of a credential is folded from its admissions and check-outs: the highest sequence is the version,
 * the admissions are the entries, the direction of the last one says whether the holder is inside.
 */
final readonly class DoctrineCheckInLedger implements CheckInLedger
{
    public const string TABLE = 'phil_checkin_scan';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function attendance(string $credentialId): Attendance
    {
        return $this->attendances([$credentialId])[$credentialId];
    }

    #[Override]
    public function attendances(array $credentialIds): array
    {
        $attendances = array_fill_keys($credentialIds, Attendance::none());
        $types = [ArrayParameterType::STRING];

        /** @var list<array{credential_id: string, version: int|string, entries: int|string, last_entry_at: int|string|null}> $counts */
        $counts = $this->connection->fetchAllAssociative(\sprintf(
            "SELECT credential_id, MAX(sequence) AS version, SUM(CASE WHEN direction = 'in' THEN 1 ELSE 0 END) AS entries, MAX(CASE WHEN direction = 'in' THEN scanned_at END) AS last_entry_at FROM %s WHERE credential_id IN (?) AND sequence IS NOT NULL GROUP BY credential_id",
            self::TABLE,
        ), [$credentialIds], $types);

        /** @var array<string, string> $last */
        $last = $this->connection->fetchAllKeyValue(\sprintf(
            'SELECT s.credential_id, s.direction FROM %1$s s INNER JOIN (SELECT credential_id, MAX(sequence) AS version FROM %1$s WHERE credential_id IN (?) AND sequence IS NOT NULL GROUP BY credential_id) l ON l.credential_id = s.credential_id AND l.version = s.sequence',
            self::TABLE,
        ), [$credentialIds], $types);

        foreach ($counts as $count) {
            $id = $count['credential_id'];
            $lastEntryAt = null === $count['last_entry_at'] ? null : new DateTimeImmutable('@'.$count['last_entry_at']);
            $attendances[$id] = Attendance::restore((int) $count['version'], (int) $count['entries'], Direction::In->value === ($last[$id] ?? null), $lastEntryAt);
        }

        return $attendances;
    }

    #[Override]
    public function find(ScanId $id): ?CheckIn
    {
        /** @var array{id: string, credential_id: string, checkpoint: string, direction: string, outcome: string, device: string, scanned_at: int|string, recorded_at: int|string, sequence: int|string|null, offline: bool|int|string, conflict: ?string}|false $row */
        $row = $this->connection->fetchAssociative('SELECT * FROM '.self::TABLE.' WHERE id = ?', [$id->value]);

        if (false === $row) {
            return null;
        }

        return CheckIn::restore(
            ScanId::of($row['id']),
            $row['credential_id'],
            CheckpointId::of($row['checkpoint']),
            Direction::from($row['direction']),
            ScanOutcome::from($row['outcome']),
            DeviceId::of($row['device']),
            new DateTimeImmutable('@'.$row['scanned_at']),
            new DateTimeImmutable('@'.$row['recorded_at']),
            null === $row['sequence'] ? null : (int) $row['sequence'],
            (bool) $row['offline'],
            null === $row['conflict'] ? null : ScanOutcome::from($row['conflict']),
        );
    }

    #[Override]
    public function append(CheckIn $checkIn): bool
    {
        try {
            $this->connection->insert(self::TABLE, [
                'id' => $checkIn->id->value,
                'credential_id' => $checkIn->credentialId,
                'sequence' => $checkIn->sequence,
                'checkpoint' => $checkIn->checkpoint->value,
                'direction' => $checkIn->direction->value,
                'outcome' => $checkIn->outcome->value,
                'conflict' => $checkIn->conflict?->value,
                'device' => $checkIn->device->value,
                'offline' => $checkIn->offline,
                'scanned_at' => $checkIn->scannedAt->getTimestamp(),
                'recorded_at' => $checkIn->recordedAt->getTimestamp(),
            ], [
                'offline' => ParameterType::BOOLEAN,
                'scanned_at' => ParameterType::INTEGER,
                'recorded_at' => ParameterType::INTEGER,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * The schema with the check-in table added, unless it is already there: for the schema the ORM generates, and for
     * an application that creates the table itself.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable(self::TABLE)) {
            return $schema;
        }

        return $schema->edit()->addTable($this->tableDefinition())->create();
    }

    private function tableDefinition(): Table
    {
        $string = static fn (string $name, int $length, bool $notNull = true): Column => Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull($notNull)->create();

        return Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                $string('id', 64),
                $string('credential_id', 128),
                Column::editor()->setUnquotedName('sequence')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                $string('checkpoint', 64),
                $string('direction', 3),
                $string('outcome', 32),
                $string('conflict', 32, false),
                $string('device', 64),
                Column::editor()->setUnquotedName('offline')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('scanned_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('recorded_at')->setTypeName(Types::BIGINT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::TABLE.'_sequence')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('credential_id', 'sequence')->create(),
                Index::editor()->setUnquotedName(self::TABLE.'_checkpoint')->setUnquotedColumnNames('checkpoint', 'scanned_at')->create(),
            )
            ->create();
    }
}
