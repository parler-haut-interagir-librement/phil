<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use Phil\CommerceLogisticsBundle\Tests\Integration\ServerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tests of the servers on SQLite, in the unit suite, which needs no server: in a file that several connections
 * share, the one `PHIL_TEST_SQLITE_PATH` names, or one of the temporary directory.
 */
#[CoversClass(DoctrineLogisticsRepository::class)]
final class SqliteLogisticsTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_SQLITE_PATH';
    }

    #[Override]
    protected function parameters(): array
    {
        $path = getenv($this->variable());

        return ['driver' => 'pdo_sqlite', 'path' => \is_string($path) && '' !== $path ? $path : sys_get_temp_dir().'/phil-commerce-logistics-bundle-test.sqlite'];
    }
}
