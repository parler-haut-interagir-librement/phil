<?php

declare(strict_types=1);

namespace Phil\Habits\Dbal;

use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Habits\Contract\HabitStore;
use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidHabit;
use Phil\Habits\Habit;
use Phil\Habits\Schedule;
use UnexpectedValueException;

/**
 * The habits in a table of a Doctrine DBAL connection, one row each, indexed by member. Days are kept as dates, the
 * schedule as its rule, the time zone by its name.
 */
final readonly class DbalHabitStore implements HabitStore
{
    public const string TABLE = 'phil_habit';

    private const string COLUMNS = 'id, member, name, schedule, time_zone, starts_on, target, archived_on';

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

        $platform = $this->connection->getDatabasePlatform();

        return $schema->edit()->addTable(
            Table::editor()
                ->setUnquotedName($this->table)
                ->setColumns(
                    TableName::identifierColumn('id', $platform),
                    TableName::identifierColumn('member', $platform),
                    Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(Habit::MAX_NAME_LENGTH)->create(),
                    Column::editor()->setUnquotedName('schedule')->setTypeName(Types::STRING)->setLength(Schedule::MAX_RULE_LENGTH)->create(),
                    Column::editor()->setUnquotedName('time_zone')->setTypeName(Types::STRING)->setLength(64)->create(),
                    Column::editor()->setUnquotedName('starts_on')->setTypeName(Types::DATE_IMMUTABLE)->create(),
                    Column::editor()->setUnquotedName('target')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                    Column::editor()->setUnquotedName('archived_on')->setTypeName(Types::DATE_IMMUTABLE)->setNotNull(false)->create(),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->setIndexes(Index::editor()->setUnquotedName($this->table.'_member')->setUnquotedColumnNames('member')->create())
                ->create(),
        )->create();
    }

    #[Override]
    public function get(string $id): ?Habit
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE id = ?', self::COLUMNS, $this->table), [$id]);

        return false === $row ? null : $this->habit($row);
    }

    #[Override]
    public function ofMember(string $member): array
    {
        return array_map(
            $this->habit(...),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE member = ? ORDER BY id', self::COLUMNS, $this->table), [$member]),
        );
    }

    #[Override]
    public function save(Habit $habit): void
    {
        $this->connection->transactional(function (Connection $connection) use ($habit): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE id = ?', $this->table), [$habit->id]);
            $connection->insert($this->table, [
                'id' => $habit->id,
                'member' => $habit->member,
                'name' => $habit->name,
                'schedule' => $habit->schedule->toString(),
                'time_zone' => $habit->timeZone->getName(),
                'starts_on' => $habit->startsOn->toString(),
                'target' => $habit->target,
                'archived_on' => $habit->archivedOn?->toString(),
            ]);
        });
    }

    #[Override]
    public function remove(string $id): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE id = ?', $this->table), [$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function habit(array $row): Habit
    {
        $target = $row['target'];
        $archivedOn = $row['archived_on'];

        return new Habit(
            $this->text($row, 'id'),
            $this->text($row, 'member'),
            $this->text($row, 'name'),
            Schedule::parse($this->text($row, 'schedule')),
            new DateTimeZone($this->text($row, 'time_zone')),
            Day::fromString($this->text($row, 'starts_on')),
            null === $target ? null : (int) $this->number($target),
            null === $archivedOn ? null : Day::fromString($this->text($row, 'archived_on')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $column): string
    {
        return \is_string($row[$column]) ? $row[$column] : throw new UnexpectedValueException(\sprintf('The column "%s" of a habit is not text.', $column));
    }

    private function number(mixed $value): int|string
    {
        return \is_int($value) || \is_string($value) && is_numeric($value) ? $value : throw new UnexpectedValueException('The target of a habit is not a number.');
    }
}
