<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Console;

use LogicException;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrationTargets;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationTarget;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\ConfiguredPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\FixedPlacements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MigrationTargets::class)]
final class MigrationTargetsTest extends TestCase
{
    public function testTheSharedDatabaseThenEachDedicatedDatabaseOnceWithItsFirstTenant(): void
    {
        $targets = new MigrationTargets(new ConfiguredPlacements(['wholesale' => 'tenant_wholesale', 'export' => 'tenant_wholesale', 'outlet' => 'tenant_outlet']));

        self::assertSame([['shared', null], ['tenant_outlet', 'outlet'], ['tenant_wholesale', 'wholesale']], $this->describe($targets->targets()));
    }

    public function testSomeDatabasesAlone(): void
    {
        $targets = new MigrationTargets(new ConfiguredPlacements(['wholesale' => 'tenant_wholesale', 'outlet' => 'tenant_outlet']));

        self::assertSame([['tenant_wholesale', 'wholesale'], ['shared', null]], $this->describe($targets->targets(['tenant_wholesale', 'shared'])));
    }

    public function testADatabaseNoTenantLivesInIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"tenant_ghost" is neither "shared" nor the database of a placed tenant.');

        new MigrationTargets(new ConfiguredPlacements())->targets(['tenant_ghost']);
    }

    public function testAPlacementThatListsNoTenantsIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The TenantPlacement service does not implement PlacedTenants: the dedicated databases cannot be listed.');

        new MigrationTargets(new FixedPlacements())->targets();
    }

    /**
     * @param list<MigrationTarget> $targets
     *
     * @return list<array{string, ?string}>
     */
    private function describe(array $targets): array
    {
        return array_map(static fn (MigrationTarget $target): array => [$target->database->toString(), $target->tenant?->value], $targets);
    }
}
