<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ShiftsBundle\Domain\Model\Absence;
use Phil\ShiftsBundle\Domain\Repository\AbsenceRepository;

/**
 * The unannounced absences through Doctrine DBAL, keyed by shift and volunteer, so that an absence recorded twice at
 * once is stored once.
 */
final readonly class DbalAbsenceRepository implements AbsenceRepository
{
    private const int MAX = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Absence $absence): bool
    {
        try {
            $this->connection->insert(ShiftsTables::ABSENCES, [
                'shift_id' => $absence->shiftId,
                'volunteer' => $absence->volunteer,
                'recorded_at' => $absence->recordedAt,
                'recorded_by' => $absence->recordedBy,
                'lifted_at' => $absence->liftedAt,
                'lifted_by' => $absence->liftedBy,
            ], ['recorded_at' => Types::DATETIME_UTC_IMMUTABLE, 'lifted_at' => Types::DATETIME_UTC_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function remove(string $shiftId, string $volunteer): bool
    {
        return 1 === $this->connection->delete(ShiftsTables::ABSENCES, ['shift_id' => $shiftId, 'volunteer' => $volunteer]);
    }

    #[Override]
    public function countSince(string $volunteer, DateTimeImmutable $since): int
    {
        return Json::int($this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.ShiftsTables::ABSENCES.' WHERE volunteer = ? AND lifted_at IS NULL AND recorded_at >= ?',
            [$volunteer, $since],
            [Types::STRING, Types::DATETIME_UTC_IMMUTABLE],
        ));
    }

    #[Override]
    public function lift(string $volunteer, string $liftedBy, DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE '.ShiftsTables::ABSENCES.' SET lifted_at = ?, lifted_by = ? WHERE volunteer = ? AND lifted_at IS NULL',
            [$at, $liftedBy, $volunteer],
            [Types::DATETIME_UTC_IMMUTABLE, Types::STRING, Types::STRING],
        );
    }

    #[Override]
    public function ofVolunteer(string $volunteer): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT shift_id, recorded_at, recorded_by, lifted_at, lifted_by FROM '.ShiftsTables::ABSENCES.' WHERE volunteer = ? ORDER BY recorded_at DESC, shift_id LIMIT '.self::MAX,
            [$volunteer],
        );

        return array_map(fn (array $row): Absence => new Absence(
            Json::string($row['shift_id']),
            $volunteer,
            Json::requiredInstant($this->connection, $row['recorded_at']),
            Json::string($row['recorded_by']),
            Json::instant($this->connection, $row['lifted_at']),
            null === $row['lifted_by'] ? null : Json::string($row['lifted_by']),
        ), $rows);
    }

    #[Override]
    public function absentFrom(string $shiftId): array
    {
        return array_map(Json::string(...), $this->connection->fetchFirstColumn(
            'SELECT volunteer FROM '.ShiftsTables::ABSENCES.' WHERE shift_id = ? ORDER BY volunteer LIMIT '.self::MAX,
            [$shiftId],
        ));
    }

    #[Override]
    public function eraseVolunteer(string $volunteer): void
    {
        $this->connection->delete(ShiftsTables::ABSENCES, ['volunteer' => $volunteer]);
    }
}
