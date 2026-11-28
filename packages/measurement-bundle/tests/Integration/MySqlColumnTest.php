<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Integration;

use Override;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The column on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(MeasurementType::class)]
final class MySqlColumnTest extends DatabaseTestCase
{
    #[Override]
    protected function url(): string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        return $url;
    }
}
