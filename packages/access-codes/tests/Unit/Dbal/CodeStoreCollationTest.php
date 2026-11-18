<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the tables compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, the default collation, which already
 * does, elsewhere.
 */
#[CoversClass(DbalCodeStore::class)]
final class CodeStoreCollationTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        $expected = [
            DbalCodeStore::BATCH_TABLE => ['id'],
            DbalCodeStore::CODE_TABLE => ['batch_id', 'hash'],
        ];
        ksort($expected);

        foreach ([DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']), DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '11.4.0-MariaDB'])] as $connection) {
            self::assertSame($expected, $this->binaryColumns(new DbalCodeStore($connection)->configureSchema(Schema::editor()->create())));
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ([DriverManager::getConnection(['driver' => 'pdo_pgsql', 'serverVersion' => '17']), DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])] as $connection) {
            foreach (new DbalCodeStore($connection)->configureSchema(Schema::editor()->create())->getTables() as $table) {
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
