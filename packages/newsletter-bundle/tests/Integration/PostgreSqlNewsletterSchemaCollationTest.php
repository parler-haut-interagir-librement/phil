<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Integration;

use Override;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(NewsletterSchema::class)]
final class PostgreSqlNewsletterSchemaCollationTest extends ServerNewsletterSchemaCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
