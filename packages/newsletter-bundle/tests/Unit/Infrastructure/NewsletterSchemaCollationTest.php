<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the tables compare byte by byte: `utf8mb4_bin` on MySQL and MariaDB, the default collation, which already
 * does, elsewhere.
 */
#[CoversClass(NewsletterSchema::class)]
final class NewsletterSchemaCollationTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        $expected = [
            NewsletterSchema::LISTS => ['id', 'list_key', 'site'],
            NewsletterSchema::SUBSCRIBERS => ['email_key', 'external_id', 'id', 'site'],
            NewsletterSchema::MEMBERSHIPS => ['list_id', 'subscriber_id'],
            NewsletterSchema::CAMPAIGNS => ['id', 'list_id', 'site'],
            NewsletterSchema::LINKS => ['campaign_id', 'url_hash'],
            NewsletterSchema::DELIVERIES => ['campaign_id', 'id', 'run_id', 'subscriber_id'],
            NewsletterSchema::TEMPLATES => ['site', 'template_key'],
            NewsletterSchema::CLICKS => ['campaign_id'],
            NewsletterSchema::RECIPIENT_CLICKS => ['campaign_id', 'subscriber_id'],
            NewsletterSchema::REASONS => ['list_id', 'site'],
            NewsletterSchema::SUPPRESSIONS => ['email_hash', 'site'],
            NewsletterSchema::SEND_RUNS => ['campaign_id', 'id'],
        ];
        ksort($expected);

        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            self::assertSame($expected, $this->binaryColumns(NewsletterSchema::configure(Schema::editor()->create(), $platform)));
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ([new PostgreSQLPlatform(), new SQLitePlatform(), null] as $platform) {
            foreach (NewsletterSchema::configure(Schema::editor()->create(), $platform)->getTables() as $table) {
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
