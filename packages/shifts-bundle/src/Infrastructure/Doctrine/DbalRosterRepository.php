<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Shifts\Format\RosterFormat;
use Phil\Shifts\Format\ShiftFormat;
use Phil\Shifts\Roster\Place;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Domain\Model\StoredRoster;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Psr\Clock\ClockInterface;

/**
 * The shifts and their rosters through Doctrine DBAL. A write is conditional on the revision read —
 * `… WHERE shift_id = ? AND revision = ?` —, which the database runs atomically, and rewrites the rows of the places in
 * the same transaction.
 */
final readonly class DbalRosterRepository implements RosterRepository
{
    private const int MAX = 500;

    private const int WRITES = 5;

    private const array TYPES = [
        'revision' => Types::INTEGER,
        'starts_at' => Types::DATETIME_UTC_IMMUTABLE,
        'ends_at' => Types::DATETIME_UTC_IMMUTABLE,
        'updated_at' => Types::DATETIME_UTC_IMMUTABLE,
    ];

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function add(Roster $roster): bool
    {
        try {
            $this->connection->transactional(function () use ($roster): void {
                $this->connection->insert(ShiftsTables::SHIFTS, ['shift_id' => $roster->shift->id, 'revision' => 1, ...$this->columns($roster)], self::TYPES);
                $this->places($roster);
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function update(Roster $roster, int $revision): bool
    {
        return $this->connection->transactional(function () use ($roster, $revision): bool {
            $updated = $this->connection->update(
                ShiftsTables::SHIFTS,
                ['revision' => $revision + 1, ...$this->columns($roster)],
                ['shift_id' => $roster->shift->id, 'revision' => $revision],
                [...self::TYPES, 'shift_id' => Types::STRING],
            );

            if (1 !== $updated) {
                return false;
            }

            $this->connection->delete(ShiftsTables::PLACES, ['shift_id' => $roster->shift->id]);
            $this->places($roster);

            return true;
        });
    }

    #[Override]
    public function find(string $shiftId): ?StoredRoster
    {
        $row = $this->connection->fetchAssociative('SELECT definition, places, revision FROM '.ShiftsTables::SHIFTS.' WHERE shift_id = ?', [$shiftId]);

        return false === $row ? null : new StoredRoster($this->roster($row), Json::int($row['revision']));
    }

    #[Override]
    public function remove(string $shiftId): void
    {
        $this->connection->transactional(function () use ($shiftId): void {
            $this->connection->delete(ShiftsTables::SWAPS, ['shift_id' => $shiftId]);
            $this->connection->delete(ShiftsTables::PLACES, ['shift_id' => $shiftId]);
            $this->connection->delete(ShiftsTables::SHIFTS, ['shift_id' => $shiftId]);
        });
    }

    #[Override]
    public function upcoming(DateTimeImmutable $after, int $limit = 200): array
    {
        return $this->rosters(
            'SELECT definition, places FROM '.ShiftsTables::SHIFTS.' WHERE ends_at > ? ORDER BY starts_at, shift_id LIMIT ?',
            [$after, max(1, min($limit, self::MAX))],
            [Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function ofVolunteer(string $volunteer): array
    {
        return $this->rosters(
            'SELECT s.definition, s.places FROM '.ShiftsTables::SHIFTS.' s INNER JOIN '.ShiftsTables::PLACES.' p ON p.shift_id = s.shift_id WHERE p.volunteer = ? ORDER BY s.starts_at, s.shift_id LIMIT '.self::MAX,
            [$volunteer],
        );
    }

    #[Override]
    public function eraseVolunteer(string $volunteer): array
    {
        $erased = [];

        foreach ($this->connection->fetchFirstColumn('SELECT shift_id FROM '.ShiftsTables::PLACES.' WHERE volunteer = ? ORDER BY shift_id', [$volunteer]) as $shiftId) {
            $shiftId = Json::string($shiftId);

            for ($write = 0;; ++$write) {
                $stored = $this->find($shiftId);

                if (!$stored instanceof StoredRoster) {
                    break;
                }

                $places = array_values(array_filter($stored->roster->places, static fn (Place $place): bool => $place->volunteer !== $volunteer));

                if ($this->update(new Roster($stored->roster->shift, $places), $stored->revision)) {
                    break;
                }

                if ($write >= self::WRITES - 1) {
                    throw new RequestRefused(RequestRefused::BUSY);
                }
            }

            $erased[] = $shiftId;
        }

        return $erased;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(Roster $roster): array
    {
        return [
            'definition' => Json::encode(ShiftFormat::toArray($roster->shift)),
            'places' => Json::encode(RosterFormat::toArray($roster)),
            'starts_at' => $roster->shift->startsAt,
            'ends_at' => $roster->shift->endsAt,
            'updated_at' => $this->clock->now(),
        ];
    }

    private function places(Roster $roster): void
    {
        foreach ($roster->places as $place) {
            $this->connection->insert(ShiftsTables::PLACES, ['shift_id' => $roster->shift->id, 'volunteer' => $place->volunteer, 'status' => $place->status->value]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function roster(array $row): Roster
    {
        return RosterFormat::fromArray(ShiftFormat::fromArray(Json::decode($row['definition'])), Json::decode($row['places']));
    }

    /**
     * @param list<mixed>                $parameters
     * @param list<ParameterType|string> $types
     *
     * @return list<Roster>
     */
    private function rosters(string $sql, array $parameters, array $types = []): array
    {
        return array_map($this->roster(...), $this->connection->fetchAllAssociative($sql, $parameters, $types));
    }
}
