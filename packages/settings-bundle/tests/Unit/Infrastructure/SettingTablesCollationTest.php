<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the tables compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, the default collation, which already
 * does, elsewhere.
 */
#[CoversClass(SettingTables::class)]
final class SettingTablesCollationTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        $expected = [
            SettingTables::SETTINGS => ['scope', 'setting_key', 'subject'],
            SettingTables::MIGRATIONS => ['version'],
        ];
        ksort($expected);

        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            self::assertSame($expected, $this->binaryColumns(SettingTables::configure(Schema::editor()->create(), $platform)));
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ([new PostgreSQLPlatform(), new SQLitePlatform(), null] as $platform) {
            foreach (SettingTables::configure(Schema::editor()->create(), $platform)->getTables() as $table) {
                foreach ($table->getColumns() as $column) {
                    self::assertNull($column->getCollation(), $column->getObjectName()->toString());
                    self::assertNull($column->getCharset(), $column->getObjectName()->toString());
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
