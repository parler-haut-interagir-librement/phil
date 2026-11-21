<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Integration;

use Override;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`.
 */
#[CoversClass(NewsletterSchema::class)]
final class MySqlNewsletterSchemaCollationTest extends ServerNewsletterSchemaCollationTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
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
        ] as $table => $columns) {
            self::assertSame($columns, $connection->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME = 'utf8mb4_bin' ORDER BY COLUMN_NAME",
                [$table],
            ), $table);
        }
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
