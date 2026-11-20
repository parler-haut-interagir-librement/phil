<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Integration;

use Override;
use Phil\CommerceBundle\Application\Refunds;
use Phil\CommerceBundle\Infrastructure\Doctrine\DoctrinePaymentLock;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The lock of a payment on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, where each statement sees what the other
 * transactions committed before it.
 */
#[CoversClass(DoctrinePaymentLock::class)]
#[CoversClass(Refunds::class)]
final class PostgreSqlRefundLockTest extends ServerRefundLockTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
