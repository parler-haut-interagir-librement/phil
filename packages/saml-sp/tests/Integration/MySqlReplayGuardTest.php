<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Integration;

use Override;
use Phil\SamlSp\Store\DbalReplayGuard;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The database replay guard on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalReplayGuard::class)]
final class MySqlReplayGuardTest extends ServerReplayGuardTestCase
{
    public function testTheKeyIsBinaryOnTheServer(): void
    {
        $this->guard();

        self::assertSame('ascii_bin', $this->connection()->fetchOne(
            "SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'assertion'",
            [DbalReplayGuard::TABLE],
        ));
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
