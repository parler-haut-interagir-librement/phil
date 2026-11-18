<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the tables compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, the default collation, which already
 * does, elsewhere.
 */
#[CoversClass(NotificationTables::class)]
final class NotificationTablesCollationTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        $expected = [
            NotificationTables::NOTIFICATIONS => ['actor', 'group_key', 'id', 'recipient', 'subject_id', 'subject_type', 'type'],
            NotificationTables::PREFERENCES => ['recipient'],
            NotificationTables::PUSH_SUBSCRIPTIONS => ['id', 'recipient'],
        ];
        ksort($expected);

        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            self::assertSame($expected, $this->binaryColumns(NotificationTables::configure(Schema::editor()->create(), true, true, $platform)));
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ([new PostgreSQLPlatform(), new SQLitePlatform(), null] as $platform) {
            foreach (NotificationTables::configure(Schema::editor()->create(), true, true, $platform)->getTables() as $table) {
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
