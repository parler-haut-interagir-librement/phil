<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotMoved;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantSwitchInTransaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TenantDatabaseUnavailable::class)]
#[CoversClass(TenantNotMoved::class)]
#[CoversClass(TenantSwitchInTransaction::class)]
final class ExceptionsTest extends TestCase
{
    public function testTheMessagesNameTheTenantAndTheDatabases(): void
    {
        $tenant = TenantId::fromString('retail');
        $dedicated = Placement::dedicated('tenant_retail');

        self::assertSame('The tenant "retail" is being moved to another database.', TenantDatabaseUnavailable::suspendedTenant($tenant)->getMessage());
        self::assertSame('The database "tenant_retail" of the tenant "retail" failed its last migration.', TenantDatabaseUnavailable::suspendedDatabase($tenant, $dedicated)->getMessage());
        self::assertSame('The tenant "retail" lives in the database "tenant_retail", but a transaction is open in "shared": commit or roll it back before entering the tenant.', TenantSwitchInTransaction::between(Placement::shared(), $dedicated, $tenant)->getMessage());
        self::assertSame('The tenant "retail" cannot be moved: the TenantPlacement service does not implement PlacementStore.', TenantNotMoved::placementIsReadOnly($tenant)->getMessage());
        self::assertSame('The tenant "retail" was not moved to "tenant_retail": the rows of invoice in the target differ from those of the source.', TenantNotMoved::verificationFailed($tenant, $dedicated, 'invoice')->getMessage());
    }

    public function testAMoveThatFailedKeepsItsCause(): void
    {
        $cause = new RuntimeException('Duplicate key.');
        $notMoved = TenantNotMoved::because(TenantId::fromString('retail'), Placement::shared(), $cause);

        self::assertSame('The tenant "retail" was not moved to "shared": Duplicate key.', $notMoved->getMessage());
        self::assertSame($cause, $notMoved->getPrevious());
        self::assertSame(0, $notMoved->getCode());
        self::assertInstanceOf(DomainError::class, $notMoved);
    }
}
