<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Integration\Infrastructure\Doctrine;

use Override;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;

/**
 * A `BINARY(16)` column on MySQL 8, through `pdo_mysql`.
 *
 *     PHIL_DDD_TEST_MYSQL_URL='mysql://root:secret@127.0.0.1:3306/probe' vendor/bin/phpunit tests/Integration
 */
#[CoversClass(IdentifierType::class)]
#[Group('mysql')]
final class MySqlIdentifierTypeTest extends IdentifierTypeRoundTripTestCase
{
    #[Override]
    protected static function urlVariable(): string
    {
        return 'PHIL_DDD_TEST_MYSQL_URL';
    }

    #[Override]
    protected function storedForm(): string
    {
        $hex = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM phil_ddd_identifier_probe');
        self::assertIsString($hex);
        self::assertSame(32, \strlen($hex), 'The column holds 16 bytes.');

        return Uuid::fromBinary((string) hex2bin($hex))->toRfc4122();
    }

    #[Override]
    protected function foreignValue(): string
    {
        return Uuid::v4()->toBinary();
    }
}
