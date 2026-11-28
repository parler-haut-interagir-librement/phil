<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Integration;

use Override;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The column on PostgreSQL, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(MeasurementType::class)]
final class PostgreSqlColumnTest extends DatabaseTestCase
{
    #[Override]
    protected function url(): string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_POSTGRESQL_URL to run this test against a real server.');
        }

        return $url;
    }
}
