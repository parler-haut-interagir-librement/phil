<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit\Dbal;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\Habits\Day;
use Phil\Habits\Dbal\DbalCheckInStore;
use Phil\Habits\Dbal\DbalHabitStore;
use Phil\Habits\Dbal\TableName;
use Phil\Habits\Exception\InvalidHabit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

/**
 * The stores on SQLite in memory: the contract, their tables, and rows the stores did not write.
 */
#[CoversClass(DbalHabitStore::class)]
#[CoversClass(DbalCheckInStore::class)]
#[CoversClass(TableName::class)]
final class SqliteStoreTest extends DbalStoreTestCase
{
    #[Override]
    protected function url(): string
    {
        return 'sqlite:///:memory:';
    }

    public function testTheTablesAreKeyedForTheirReads(): void
    {
        $schema = $this->connection()->createSchemaManager()->introspectSchema();

        $habits = $schema->getTable('phil_habit');
        self::assertSame(['id'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $habits->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['member'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $habits->getIndex('phil_habit_member')->getIndexedColumns()));
        self::assertSame(['id', 'member', 'name', 'schedule', 'time_zone', 'starts_on', 'target', 'archived_on'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $habits->getColumns()));
        self::assertSame(200, $habits->getColumn('name')->getLength());
        self::assertSame(64, $habits->getColumn('schedule')->getLength());
        self::assertSame(255, $habits->getColumn('member')->getLength());
        self::assertSame(64, $habits->getColumn('time_zone')->getLength());
        self::assertFalse($habits->getColumn('target')->getNotnull());
        self::assertFalse($habits->getColumn('archived_on')->getNotnull());
        self::assertTrue($habits->getColumn('starts_on')->getNotnull());

        $checkIns = $schema->getTable('phil_habit_check_in');
        self::assertSame(['habit', 'day'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $checkIns->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['habit', 'day', 'status', 'value', 'recorded_at'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $checkIns->getColumns()));
        self::assertSame(16, $checkIns->getColumn('status')->getLength());
        self::assertFalse($checkIns->getColumn('value')->getNotnull());
        self::assertTrue($checkIns->getColumn('recorded_at')->getNotnull());
    }

    public function testTheTablesCanBeNamedAndATableAlreadyThereIsKept(): void
    {
        $habits = new DbalHabitStore($this->connection(), 'my_habit');
        $checkIns = new DbalCheckInStore($this->connection(), 'my_check_in');
        $schema = $checkIns->configureSchema($habits->configureSchema(Schema::editor()->create()));

        self::assertTrue($schema->hasTable('my_habit'));
        self::assertTrue($schema->hasTable('my_check_in'));
        self::assertTrue($schema->getTable('my_habit')->hasIndex('my_habit_member'));
        self::assertSame($schema, $habits->configureSchema($schema));
        self::assertSame($schema, $checkIns->configureSchema($schema));

        foreach ($schema->toSql($this->connection()->getDatabasePlatform()) as $statement) {
            $this->connection()->executeStatement($statement);
        }

        $this->connection()->insert('my_check_in', ['habit' => 'h', 'day' => '2026-11-02', 'status' => 'done', 'value' => null, 'recorded_at' => 0]);
        self::assertNotNull($checkIns->get('h', Day::fromString('2026-11-02')));
        self::assertNull(new DbalCheckInStore($this->connection())->get('h', Day::fromString('2026-11-02')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTableNames(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1habit'];
        yield 'capitals' => ['Habit'];
        yield 'a statement' => ['habit; DROP TABLE x'];
        yield 'a new line after' => ["habit\n"];
        yield 'too long' => [str_repeat('h', 64)];
    }

    #[DataProvider('invalidTableNames')]
    public function testATableNameIsChecked(string $name): void
    {
        $this->expectExceptionObject(InvalidHabit::because('A table name is up to 63 lowercase letters, digits or "_", not starting with a digit.'));

        new DbalHabitStore($this->connection(), $name);
    }

    public function testACheckInTableNameIsChecked(): void
    {
        $this->expectException(InvalidHabit::class);

        new DbalCheckInStore($this->connection(), 'Check-ins');
    }

    public function testTheLongestTableNameIsTaken(): void
    {
        self::assertSame(str_repeat('h', 63), TableName::of(str_repeat('h', 63)));
        self::assertSame('_h1', TableName::of('_h1'));
    }

    public function testATargetReadAsTextIsANumber(): void
    {
        $this->connection()->insert('phil_habit', ['id' => 'h', 'member' => 'm', 'name' => 'Read', 'schedule' => 'daily', 'time_zone' => 'UTC', 'starts_on' => '2026-11-02', 'target' => '12', 'archived_on' => null]);

        self::assertSame(12, new DbalHabitStore($this->connection())->get('h')?->target);
    }

    public function testATargetStoredAsTextIsReadAsAWholeNumber(): void
    {
        $this->connection()->executeStatement('DROP TABLE phil_habit');
        $this->connection()->executeStatement('CREATE TABLE phil_habit (id TEXT, member TEXT, name, schedule TEXT, time_zone TEXT, starts_on TEXT, target, archived_on TEXT)');
        $this->connection()->insert('phil_habit', ['id' => 'h', 'member' => 'm', 'name' => 'Read', 'schedule' => 'daily', 'time_zone' => 'UTC', 'starts_on' => '2026-11-02', 'target' => '12', 'archived_on' => null]);

        self::assertSame(12, new DbalHabitStore($this->connection())->get('h')?->target);
    }

    public function testACheckInRowWithoutATextHabitIsRefused(): void
    {
        $this->connection()->executeStatement('DROP TABLE phil_habit_check_in');
        $this->connection()->executeStatement('CREATE TABLE phil_habit_check_in (habit NUMERIC, day TEXT, status TEXT, value, recorded_at)');
        $this->connection()->insert('phil_habit_check_in', ['habit' => 5, 'day' => '2026-11-02', 'status' => 'done', 'value' => null, 'recorded_at' => 0]);

        $this->expectExceptionObject(new UnexpectedValueException('A check-in row has no habit, day or status as text.'));

        new DbalCheckInStore($this->connection())->get('5', Day::fromString('2026-11-02'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function corruptHabits(): iterable
    {
        yield 'a name not text' => [['name' => null], 'The column "name" of a habit is not text.'];
        yield 'a target not a number' => [['target' => 'twelve'], 'The target of a habit is not a number.'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('corruptHabits')]
    public function testAHabitRowOfTheWrongTypeIsRefused(array $values, string $message): void
    {
        $this->connection()->executeStatement('DROP TABLE phil_habit');
        $this->connection()->executeStatement('CREATE TABLE phil_habit (id TEXT, member TEXT, name, schedule TEXT, time_zone TEXT, starts_on TEXT, target, archived_on TEXT)');
        $this->connection()->insert('phil_habit', [...['id' => 'h', 'member' => 'm', 'name' => 'Read', 'schedule' => 'daily', 'time_zone' => 'UTC', 'starts_on' => '2026-11-02', 'target' => null, 'archived_on' => null], ...$values]);

        $this->expectExceptionObject(new UnexpectedValueException($message));

        new DbalHabitStore($this->connection())->get('h');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function corruptCheckIns(): iterable
    {
        yield 'a status not text' => [['status' => null], 'A check-in row has no habit, day or status as text.'];
        yield 'an unknown status' => [['status' => 'maybe'], 'A check-in row has an unknown status.'];
        yield 'a value not whole' => [['value' => '1.5'], 'A number of a check-in row is not a whole number.'];
        yield 'an instant not whole' => [['recorded_at' => 'now'], 'A number of a check-in row is not a whole number.'];
        yield 'an instant with a new line' => [['recorded_at' => "12\n"], 'A number of a check-in row is not a whole number.'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('corruptCheckIns')]
    public function testACheckInRowOfTheWrongTypeIsRefused(array $values, string $message): void
    {
        $this->connection()->executeStatement('DROP TABLE phil_habit_check_in');
        $this->connection()->executeStatement('CREATE TABLE phil_habit_check_in (habit TEXT, day TEXT, status, value, recorded_at)');
        $this->connection()->insert('phil_habit_check_in', [...['habit' => 'h', 'day' => '2026-11-02', 'status' => 'done', 'value' => null, 'recorded_at' => 0], ...$values]);

        $this->expectExceptionObject(new UnexpectedValueException($message));

        new DbalCheckInStore($this->connection())->get('h', Day::fromString('2026-11-02'));
    }

    public function testNumbersReadAsTextAreWhole(): void
    {
        $this->connection()->executeStatement('DROP TABLE phil_habit_check_in');
        $this->connection()->executeStatement('CREATE TABLE phil_habit_check_in (habit TEXT, day TEXT, status, value, recorded_at)');
        $this->connection()->insert('phil_habit_check_in', ['habit' => 'h', 'day' => '2026-11-02', 'status' => 'partial', 'value' => '7', 'recorded_at' => '-5']);

        $checkIn = new DbalCheckInStore($this->connection())->get('h', Day::fromString('2026-11-02'));
        self::assertSame(7, $checkIn?->value);
        self::assertSame(-5, $checkIn->recordedAt->getTimestamp());
    }
}
