<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Integration;

use Override;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLock;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The lock of a payment on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose repeatable reads keep the snapshot of a
 * transaction's first read.
 */
#[CoversClass(DoctrinePaymentLock::class)]
#[CoversClass(Refunds::class)]
final class MySqlRefundLockTest extends ServerRefundLockTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
