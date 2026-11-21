<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Override;
use Phil\AiBundle\Application\Quota;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The concurrent admissions on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(Quota::class)]
final class MySqlConcurrentAdmissionTest extends ConcurrentAdmissionTestCase
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
