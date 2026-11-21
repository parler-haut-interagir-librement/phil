<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Integration;

use Override;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalErasureRequests::class)]
final class PostgreSqlErasureRequestsTest extends ServerErasureRequestsTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
