<?php

declare(strict_types=1);

namespace Phil\Habits\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Habits\CheckIn;
use Phil\Habits\CheckInStatus;
use Phil\Habits\Contract\CheckInStore;
use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidHabit;
use UnexpectedValueException;

/**
 * The check-ins in a table of a Doctrine DBAL connection, one row per habit and day, keyed on both so that a habit's
 * days are read in one range scan. The day is kept as a date, the instant it was recorded as a Unix timestamp, in UTC.
 */
final readonly class DbalCheckInStore implements CheckInStore
{
    public const string TABLE = 'phil_habit_check_in';

    private const string COLUMNS = 'habit, day, status, value, recorded_at';

    /** @var non-empty-string */
    private string $table;

    /**
     * @throws InvalidHabit when the table name is not one
     */
    public function __construct(
        private Connection $connection,
        string $table = self::TABLE,
    ) {
        $this->table = TableName::of($table);
    }

    /**
     * Adds the table to a schema that lacks it: one a migration builds, or the one the ORM generates.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        return $schema->edit()->addTable(
            Table::editor()
                ->setUnquotedName($this->table)
                ->setColumns(
                    TableName::identifierColumn('habit', $this->connection->getDatabasePlatform()),
                    Column::editor()->setUnquotedName('day')->setTypeName(Types::DATE_IMMUTABLE)->create(),
                    Column::editor()->setUnquotedName('status')->setTypeName(Types::STRING)->setLength(16)->create(),
                    Column::editor()->setUnquotedName('value')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
                    Column::editor()->setUnquotedName('recorded_at')->setTypeName(Types::BIGINT)->create(),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('habit', 'day')->create())
                ->create(),
        )->create();
    }

    #[Override]
    public function get(string $habit, Day $day): ?CheckIn
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s WHERE habit = ? AND day = ?', self::COLUMNS, $this->table),
            [$habit, $day->toString()],
        );

        return false === $row ? null : $this->checkIn($row);
    }

    #[Override]
    public function between(string $habit, Day $from, Day $through): array
    {
        return array_map($this->checkIn(...), $this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE habit = ? AND day >= ? AND day <= ? ORDER BY day', self::COLUMNS, $this->table),
            [$habit, $from->toString(), $through->toString()],
        ));
    }

    #[Override]
    public function save(CheckIn $checkIn): void
    {
        $this->connection->transactional(function (Connection $connection) use ($checkIn): void {
            $connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE habit = ? AND day = ?', $this->table),
                [$checkIn->habit, $checkIn->day->toString()],
            );
            $connection->executeStatement(
                \sprintf('INSERT INTO %s (%s) VALUES (?, ?, ?, ?, ?)', $this->table, self::COLUMNS),
                [$checkIn->habit, $checkIn->day->toString(), $checkIn->status->value, $checkIn->value, $checkIn->recordedAt->getTimestamp()],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, null === $checkIn->value ? ParameterType::NULL : ParameterType::INTEGER, ParameterType::INTEGER],
            );
        });
    }

    #[Override]
    public function remove(string $habit, Day $day): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE habit = ? AND day = ?', $this->table), [$habit, $day->toString()]);
    }

    #[Override]
    public function removeHabit(string $habit): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE habit = ?', $this->table), [$habit]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function checkIn(array $row): CheckIn
    {
        [$habit, $day, $status] = [$row['habit'], $row['day'], $row['status']];

        if (!\is_string($habit) || !\is_string($day) || !\is_string($status)) {
            throw new UnexpectedValueException('A check-in row has no habit, day or status as text.');
        }

        return new CheckIn(
            $habit,
            Day::fromString($day),
            CheckInStatus::tryFrom($status) ?? throw new UnexpectedValueException('A check-in row has an unknown status.'),
            null === $row['value'] ? null : $this->integer($row['value']),
            new DateTimeImmutable('@'.$this->integer($row['recorded_at'])),
        );
    }

    private function integer(mixed $value): int
    {
        if (\is_int($value) || \is_string($value) && 1 === preg_match('/^-?\d+$/D', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException('A number of a check-in row is not a whole number.');
    }
}
