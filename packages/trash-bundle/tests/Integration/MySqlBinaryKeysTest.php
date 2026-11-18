<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Integration;

use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\TrashBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`, and the tables of an earlier version reach it through the schema diff.
 */
#[CoversClass(BinaryKeysListener::class)]
final class MySqlBinaryKeysTest extends ServerBinaryKeysTestCase
{
    private const array BINARY = [
        'phil_trash_item' => ['id', 'parent', 'resource_id', 'resource_key', 'trashed_by'],
    ];

    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $this->createTables($this->schema(true));

        self::assertSame(self::BINARY, $this->binaryColumns());
    }

    public function testTheSchemaDiffBringsTheTablesOfAnEarlierVersionToTheBinaryCollation(): void
    {
        $connection = $this->connection();
        $this->createTables($this->schema(false));
        self::assertSame(array_fill_keys(array_keys(self::BINARY), []), $this->binaryColumns());

        $manager = $connection->createSchemaManager();
        $statements = [];

        foreach ($this->schema(true)->getTables() as $table) {
            $diff = $manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($this->nameOf($table)), $table);
            $statements = [...$statements, ...$connection->getDatabasePlatform()->getAlterTableSQL($diff)];
        }

        self::assertNotSame([], $statements);
        // The foreign keys towards a converted identifier are checked again once both sides are converted.
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($statements as $statement) {
                $connection->executeStatement($statement);
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        self::assertSame(self::BINARY, $this->binaryColumns());

        foreach ($this->schema(true)->getTables() as $table) {
            self::assertTrue($manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($this->nameOf($table)), $table)->isEmpty(), $this->nameOf($table));
        }
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }

    /**
     * @return array<string, list<string>>
     */
    private function binaryColumns(): array
    {
        $binary = [];

        foreach (array_keys(self::BINARY) as $table) {
            $binary[$table] = array_values(array_filter($this->connection()->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME = 'utf8mb4_bin' ORDER BY COLUMN_NAME",
                [$table],
            ), \is_string(...)));
        }

        return $binary;
    }

    /**
     * @return non-empty-string
     */
    private function nameOf(Table $table): string
    {
        $name = $table->getObjectName()->toString();
        self::assertNotSame('', $name);

        return $name;
    }
}
