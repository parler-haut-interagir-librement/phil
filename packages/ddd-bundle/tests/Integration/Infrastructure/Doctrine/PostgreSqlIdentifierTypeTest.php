<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Integration\Infrastructure\Doctrine;

use Override;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;

/**
 * A native `uuid` column on PostgreSQL, through `pdo_pgsql`.
 *
 *     PHIL_DDD_TEST_POSTGRESQL_URL='postgresql://user:password@127.0.0.1:5432/probe' vendor/bin/phpunit tests/Integration
 */
#[CoversClass(IdentifierType::class)]
#[Group('postgresql')]
final class PostgreSqlIdentifierTypeTest extends IdentifierTypeRoundTripTestCase
{
    #[Override]
    protected static function urlVariable(): string
    {
        return 'PHIL_DDD_TEST_POSTGRESQL_URL';
    }

    #[Override]
    protected function storedForm(): string
    {
        self::assertSame('uuid', $this->connection->fetchOne('SELECT pg_typeof(id)::text FROM phil_ddd_identifier_probe'));

        $text = $this->connection->fetchOne('SELECT id::text FROM phil_ddd_identifier_probe');
        self::assertIsString($text);

        return $text;
    }

    #[Override]
    protected function foreignValue(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
