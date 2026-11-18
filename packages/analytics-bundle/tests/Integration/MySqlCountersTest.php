<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterSchemaListener;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterTables;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterRollup;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The counters on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents; and the
 * tables of an earlier install, in that collation, brought to `utf8mb4_bin` by the schema diff.
 */
#[CoversClass(CounterTables::class)]
#[CoversClass(DbalCounterStore::class)]
#[CoversClass(DbalCounterRollup::class)]
#[CoversClass(CounterSchemaListener::class)]
final class MySqlCountersTest extends ServerCountersTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        self::assertSame(
            ['dimension' => 'utf8mb4_bin', 'dimension_value' => 'utf8mb4_bin', 'metric' => 'utf8mb4_bin', 'site' => 'utf8mb4_bin'],
            $this->binaryColumns($this->connection(), CounterTables::DAILY),
        );
        self::assertCount(4, $this->binaryColumns($this->connection(), CounterTables::MONTHLY));
    }

    public function testTheTablesOfAnEarlierInstallAreAlteredByTheSchemaDiff(): void
    {
        $connection = $this->connection();

        foreach ([CounterTables::DAILY, CounterTables::MONTHLY] as $table) {
            $connection->executeStatement('DROP TABLE '.$table);
        }

        $manager = $connection->createSchemaManager();

        foreach (CounterTables::configure(Schema::editor()->create())->getTables() as $table) {
            $manager->createTable($table);
        }

        $connection->insert(CounterTables::DAILY, ['site' => 'acme', 'day' => '2026-11-10', 'metric' => 'outbound_link', 'dimension' => 'link', 'dimension_value' => 'ticket-page', 'n' => 4]);

        self::assertSame([], $this->binaryColumns($connection, CounterTables::DAILY));
        self::assertSame([4], $connection->fetchFirstColumn('SELECT n FROM '.CounterTables::DAILY." WHERE metric = 'OUTBOUND_LINK'"));

        $target = CounterTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform());

        foreach ([CounterTables::DAILY, CounterTables::MONTHLY] as $table) {
            $diff = $manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($table), $target->getTable($table));

            self::assertFalse($diff->isEmpty(), $table);

            $manager->alterTable($diff);
        }

        self::assertCount(4, $this->binaryColumns($connection, CounterTables::DAILY));
        self::assertCount(4, $this->binaryColumns($connection, CounterTables::MONTHLY));
        self::assertSame([], $connection->fetchFirstColumn('SELECT n FROM '.CounterTables::DAILY." WHERE metric = 'OUTBOUND_LINK'"));
        self::assertSame([4], $connection->fetchFirstColumn('SELECT n FROM '.CounterTables::DAILY." WHERE metric = 'outbound_link'"));

        foreach ([CounterTables::DAILY, CounterTables::MONTHLY] as $table) {
            self::assertTrue($manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($table), $target->getTable($table))->isEmpty(), $table);
        }
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }

    /**
     * @return array<string, string>
     */
    private function binaryColumns(Connection $connection, string $table): array
    {
        /** @var array<string, string> $columns */
        $columns = $connection->fetchAllKeyValue(
            "SELECT COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME = 'utf8mb4_bin' ORDER BY COLUMN_NAME",
            [$table],
        );

        return $columns;
    }
}
