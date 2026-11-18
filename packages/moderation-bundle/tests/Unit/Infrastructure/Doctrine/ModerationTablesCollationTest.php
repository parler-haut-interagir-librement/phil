<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the tables compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, the default collation, which already
 * does, elsewhere.
 */
#[CoversClass(ModerationTables::class)]
final class ModerationTablesCollationTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        $expected = [
            ModerationTables::REPORTS => ['decision_id', 'id', 'reporter', 'target_id', 'target_type'],
            ModerationTables::DECISIONS => ['author', 'id', 'moderator', 'target_id', 'target_type'],
            ModerationTables::NOTES => ['author', 'id', 'member'],
            ModerationTables::SANDBOX => ['member'],
            ModerationTables::STATEMENTS => ['decision_id', 'id', 'recipient', 'target_id', 'target_type'],
        ];
        ksort($expected);

        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            self::assertSame($expected, $this->binaryColumns(ModerationTables::configure(Schema::editor()->create(), $platform)));
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ([new PostgreSQLPlatform(), new SQLitePlatform(), null] as $platform) {
            foreach (ModerationTables::configure(Schema::editor()->create(), $platform)->getTables() as $table) {
                foreach ($table->getColumns() as $column) {
                    self::assertNull($column->getCollation(), $column->getObjectName()->toString());
                    self::assertNull($column->getCharset(), $column->getObjectName()->toString());
                }
            }
        }
    }

    public function testTheColumnsOfThePrimaryKeysHoldAValue(): void
    {
        foreach ([new MySQLPlatform(), new SQLitePlatform(), null] as $platform) {
            foreach (ModerationTables::configure(Schema::editor()->create(), $platform)->getTables() as $table) {
                foreach ($table->getPrimaryKeyConstraint()?->getColumnNames() ?? [] as $name) {
                    self::assertTrue($table->getColumn($name->toString())->getNotnull(), $table->getObjectName()->toString().'.'.$name->toString());
                }
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function binaryColumns(Schema $schema): array
    {
        $binary = [];

        foreach ($schema->getTables() as $table) {
            $columns = [];

            foreach ($table->getColumns() as $column) {
                if ('utf8mb4_bin' === $column->getCollation()) {
                    self::assertSame('utf8mb4', $column->getCharset());
                    $columns[] = $column->getObjectName()->toString();
                }
            }

            sort($columns);
            $binary[$table->getObjectName()->toString()] = $columns;
        }

        ksort($binary);

        return $binary;
    }
}
