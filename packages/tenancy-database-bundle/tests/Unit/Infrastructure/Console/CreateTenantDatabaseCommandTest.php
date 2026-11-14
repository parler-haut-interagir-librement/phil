<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Console;

use Doctrine\DBAL\DriverManager;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\CreateTenantDatabaseCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Database\TenantDatabaseCreator;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\FixedPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CreateTenantDatabaseCommand::class)]
final class CreateTenantDatabaseCommandTest extends TestCase
{
    public function testATenantOfTheSharedDatabaseNeedsNothing(): void
    {
        $command = new CreateTenantDatabaseCommand(
            new FixedPlacements(),
            new TenantDatabaseCreator(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), new TenantDatabaseRouter()),
            new MigrationRunner(['console'], new MemoryAvailability()),
        );
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute(['tenant' => 'retail']));
        self::assertSame("The tenant \"retail\" lives in the shared database: there is nothing to create.\n", $tester->getDisplay());
    }
}
