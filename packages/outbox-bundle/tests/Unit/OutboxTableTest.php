<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use InvalidArgumentException;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutboxTable::class)]
final class OutboxTableTest extends TestCase
{
    public function testTheTableKeepsTheRecordsInTheOrderTheyWereAdded(): void
    {
        $table = new OutboxTable()->definition(new SQLitePlatform());

        self::assertSame('phil_outbox', $table->getObjectName()->toString());
        self::assertSame(
            ['position', 'id', 'type', 'payload', 'ordering_key', 'idempotency_key', 'state', 'attempts', 'occurred_at', 'available_at', 'claimed_by', 'lease_until', 'last_error', 'settled_at'],
            array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $table->getColumns()),
        );
        self::assertTrue($table->getColumn('position')->getAutoincrement());
        self::assertSame(['position'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(32, $table->getColumn('id')->getLength());
        self::assertSame(120, $table->getColumn('type')->getLength());
        self::assertSame(190, $table->getColumn('ordering_key')->getLength());
        self::assertSame(190, $table->getColumn('idempotency_key')->getLength());
        self::assertSame(16, $table->getColumn('state')->getLength());
        self::assertSame(32, $table->getColumn('claimed_by')->getLength());
        self::assertSame(500, $table->getColumn('last_error')->getLength());
        self::assertFalse($table->getColumn('ordering_key')->getNotnull());
        self::assertFalse($table->getColumn('idempotency_key')->getNotnull());
        self::assertFalse($table->getColumn('claimed_by')->getNotnull());
        self::assertFalse($table->getColumn('lease_until')->getNotnull());
        self::assertFalse($table->getColumn('last_error')->getNotnull());
        self::assertFalse($table->getColumn('settled_at')->getNotnull());
        self::assertTrue($table->getColumn('id')->getNotnull());
        self::assertTrue($table->getColumn('type')->getNotnull());
        self::assertTrue($table->getColumn('state')->getNotnull());

        $unique = [];

        foreach ($table->getUniqueConstraints() as $constraint) {
            $unique[$constraint->getObjectName()?->toString() ?? ''] = array_map(static fn (UnqualifiedName $name): string => $name->toString(), $constraint->getColumnNames());
        }

        self::assertSame(['phil_outbox_id' => ['id'], 'phil_outbox_idempotency' => ['type', 'idempotency_key']], $unique);

        $indexes = [];

        foreach ($table->getIndexes() as $index) {
            $indexes[$index->getObjectName()->toString()] = array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns());
        }

        self::assertSame(['state', 'available_at', 'position'], $indexes['phil_outbox_due']);
        self::assertSame(['ordering_key', 'position'], $indexes['phil_outbox_ordering']);
        self::assertSame(['claimed_by'], $indexes['phil_outbox_claim']);
        self::assertSame(['state', 'settled_at'], $indexes['phil_outbox_settled']);
    }

    public function testTheKeysCompareByteByByteOnEveryPlatform(): void
    {
        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            $column = new OutboxTable()->definition($platform)->getColumn('ordering_key');
            self::assertSame('ascii', $column->getCharset());
            self::assertSame('ascii_bin', $column->getCollation());
        }

        self::assertSame('C', new OutboxTable()->definition(new PostgreSQLPlatform())->getColumn('idempotency_key')->getCollation());
        self::assertNull(new OutboxTable()->definition(new SQLitePlatform())->getColumn('type')->getCollation());
        self::assertNull(new OutboxTable()->definition(new MySQLPlatform())->getColumn('last_error')->getCollation());
    }

    public function testTheTableIsAddedToASchemaThatLacksIt(): void
    {
        $outbox = new OutboxTable('events_out');
        $schema = $outbox->configureSchema(Schema::editor()->create(), new SQLitePlatform());

        self::assertTrue($schema->hasTable('events_out'));
        self::assertSame($schema, $outbox->configureSchema($schema, new SQLitePlatform()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedNames(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1outbox'];
        yield 'uppercase' => ['Outbox'];
        yield 'a dash' => ['phil-outbox'];
        yield 'too long' => [str_repeat('a', 49)];
        yield 'much too long' => [str_repeat('a', 70)];
        yield 'a newline' => ["outbox\n"];
    }

    #[DataProvider('refusedNames')]
    public function testANameOfAnotherShapeIsRefused(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a table name: 1 to 48 lowercase letters, digits or "_", not starting with a digit.', mb_strimwidth($name, 0, 60, '…')));

        new OutboxTable($name);
    }

    public function testTheLongestNameIsAccepted(): void
    {
        self::assertSame('_'.str_repeat('a', 47), new OutboxTable('_'.str_repeat('a', 47))->name);
    }
}
