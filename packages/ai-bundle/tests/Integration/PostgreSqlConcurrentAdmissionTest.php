<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Override;
use Phil\AiBundle\Application\Quota;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The concurrent admissions on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(Quota::class)]
final class PostgreSqlConcurrentAdmissionTest extends ConcurrentAdmissionTestCase
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
