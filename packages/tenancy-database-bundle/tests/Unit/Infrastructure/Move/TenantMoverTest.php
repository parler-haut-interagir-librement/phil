<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Move;

use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotMoved;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\MoveReport;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\TenantMover;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\ConfiguredPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Sqlite;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\StoredPlacements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(TenantMover::class)]
#[CoversClass(MoveReport::class)]
final class TenantMoverTest extends TestCase
{
    public function testAReadOnlyPlacementCannotMoveATenant(): void
    {
        $this->expectException(TenantNotMoved::class);
        $this->expectExceptionMessageIsOrContains('The tenant "retail" cannot be moved: the TenantPlacement service does not implement PlacementStore.');

        $this->mover(new ConfiguredPlacements())->move(TenantId::fromString('retail'), Placement::dedicated('tenant_retail'));
    }

    public function testATenantAlreadyInTheTargetIsNotMoved(): void
    {
        $availability = new MemoryAvailability();
        $report = $this->mover(new StoredPlacements(['wholesale' => 'tenant_wholesale']), $availability)->move(TenantId::fromString('wholesale'), Placement::dedicated('tenant_wholesale'));

        self::assertFalse($report->moved());
        self::assertSame([], $report->rows);
        self::assertFalse($report->sourcePurged);
        self::assertSame('wholesale', $report->tenant->value);
        self::assertSame([], $availability->tenants, 'The tenant was never suspended.');
    }

    public function testTheRowsLeftInTheSourceAreRemovedUnlessTheCallerKeepsThem(): void
    {
        $parameters = new ReflectionMethod(TenantMover::class, 'move')->getParameters();

        self::assertSame('keepSource', $parameters[2]->getName());
        self::assertFalse($parameters[2]->getDefaultValue());
    }

    public function testAReportOfAMove(): void
    {
        $report = new MoveReport(TenantId::fromString('retail'), Placement::shared(), Placement::dedicated('tenant_retail'), ['invoice' => 2], true);

        self::assertTrue($report->moved());
    }

    private function mover(TenantPlacement $placement, ?MemoryAvailability $availability = null): TenantMover
    {
        $entityManager = Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Plan');

        return new TenantMover($entityManager->getConnection(), new TenantDatabaseRouter(), $entityManager, $placement, $availability ?? new MemoryAvailability(), new TenantContext());
    }
}
