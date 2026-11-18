<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Only the columns that may lack a value are nullable, whatever the platform.
 */
#[CoversClass(NewsletterSchema::class)]
final class NewsletterSchemaNullabilityTest extends TestCase
{
    public function testOnlyTheOptionalColumnsAreNullable(): void
    {
        $expected = [
            NewsletterSchema::SUBSCRIBERS => ['confirmed_at', 'external_id', 'paused_until'],
            NewsletterSchema::CAMPAIGNS => ['scheduled_at', 'segment', 'sender_profile', 'sent_at', 'template'],
            NewsletterSchema::DELIVERIES => ['campaign_id', 'sent_at', 'template'],
        ];
        ksort($expected);

        foreach ([new MySQLPlatform(), new PostgreSQLPlatform(), new SQLitePlatform(), null] as $platform) {
            $nullable = [];

            foreach (NewsletterSchema::configure(Schema::editor()->create(), $platform)->getTables() as $table) {
                $columns = [];

                foreach ($table->getColumns() as $column) {
                    if (!$column->getNotnull()) {
                        $columns[] = $column->getObjectName()->toString();
                    }
                }

                if ([] !== $columns) {
                    sort($columns);
                    $nullable[$table->getObjectName()->toString()] = $columns;
                }
            }

            ksort($nullable);
            self::assertSame($expected, $nullable);
        }
    }
}
