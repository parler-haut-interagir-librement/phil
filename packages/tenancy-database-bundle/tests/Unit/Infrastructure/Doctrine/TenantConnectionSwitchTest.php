<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantSwitchInTransaction;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantConnectionSwitch;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\FixedPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Sqlite;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Switch\Note;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(TenantConnectionSwitch::class)]
final class TenantConnectionSwitchTest extends TestCase
{
    private EntityManager $entityManager;

    private EntityManager $otherManager;

    private TenantDatabaseRouter $router;

    private RecordingLogger $logger;

    private TenantConnectionSwitch $switch;

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Switch');
        $this->otherManager = Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Switch');
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->router = new TenantDatabaseRouter();
        $this->logger = new RecordingLogger();
        $this->switch = new TenantConnectionSwitch(
            $this->entityManager->getConnection(),
            $this->router,
            new FixedPlacements(['wholesale' => 'tenant_wholesale', 'export' => 'tenant_wholesale']),
            $this->registry([$this->entityManager, $this->otherManager, self::createStub(ObjectManager::class)]),
            $this->logger,
        );
    }

    public function testATenantOfTheSharedDatabaseKeepsTheConnectionOpen(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->fetchOne('SELECT 1');

        $this->switch->activate(TenantId::fromString('retail'));

        self::assertTrue($connection->isConnected());
        self::assertTrue($this->router->current()->isShared());
        self::assertSame([], $this->logger->records);
    }

    public function testATenantOfAnotherDatabaseClosesTheConnectionAndRoutesIt(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->fetchOne('SELECT 1');

        $this->switch->activate(TenantId::fromString('wholesale'));

        self::assertFalse($connection->isConnected());
        self::assertSame('tenant_wholesale', $this->router->current()->database);
        self::assertSame([[LogLevel::DEBUG, 'The connection follows the tenant "{tenant}" to the database "{database}".', ['tenant' => 'wholesale', 'database' => 'tenant_wholesale']]], $this->logger->records);
    }

    public function testEachChangeOfTenantClearsTheManagersOfTheConnectionOnly(): void
    {
        $this->switch->activate(TenantId::fromString('retail'));
        $note = $this->managed($this->entityManager);
        $other = $this->managed($this->otherManager);

        $this->switch->activate(TenantId::fromString('retail'));
        self::assertTrue($this->entityManager->contains($note), 'The same tenant again keeps the identity map.');

        $this->switch->activate(TenantId::fromString('outlet'));
        self::assertFalse($this->entityManager->contains($note), 'Another tenant of the same database clears it.');
        self::assertTrue($this->otherManager->contains($other), 'A manager of another connection is left alone.');

        $note = $this->managed($this->entityManager);
        $this->switch->activate(TenantId::fromString('wholesale'));
        self::assertFalse($this->entityManager->contains($note));
    }

    public function testTwoTenantsOfOneDedicatedDatabaseShareTheConnection(): void
    {
        $this->switch->activate(TenantId::fromString('wholesale'));
        $connection = $this->entityManager->getConnection();
        $connection->fetchOne('SELECT 1');

        $this->switch->activate(TenantId::fromString('export'));

        self::assertTrue($connection->isConnected());
    }

    public function testAnotherDatabaseIsRefusedInsideATransaction(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->switch->activate(TenantId::fromString('wholesale'));
            self::fail('The switch was not refused.');
        } catch (TenantSwitchInTransaction) {
            self::assertTrue($connection->isTransactionActive());
            self::assertTrue($this->router->current()->isShared());
        }

        $this->switch->activate(TenantId::fromString('outlet'));
        self::assertTrue($connection->isTransactionActive(), 'A tenant of the same database is entered inside the transaction.');
        $connection->rollBack();
    }

    public function testLeavingADedicatedTenantReturnsToTheSharedDatabase(): void
    {
        $this->switch->activate(TenantId::fromString('wholesale'));
        $note = $this->managed($this->entityManager);
        $connection = $this->entityManager->getConnection();
        $connection->fetchOne('SELECT 1');

        $this->switch->deactivate();

        self::assertTrue($this->router->current()->isShared());
        self::assertFalse($connection->isConnected());
        self::assertFalse($this->entityManager->contains($note));
        self::assertCount(1, $this->logger->records, 'No warning without a transaction.');
    }

    public function testLeavingASharedTenantClearsTheManagersAndKeepsTheConnection(): void
    {
        $this->switch->activate(TenantId::fromString('retail'));
        $note = $this->managed($this->entityManager);
        $connection = $this->entityManager->getConnection();
        $connection->fetchOne('SELECT 1');

        $this->switch->deactivate();

        self::assertTrue($connection->isConnected());
        self::assertFalse($this->entityManager->contains($note));
    }

    public function testLeavingNoTenantDoesNothing(): void
    {
        $note = $this->managed($this->entityManager);

        $this->switch->deactivate();

        self::assertTrue($this->entityManager->contains($note));
    }

    public function testATransactionLeftOpenIsAbandonedWithAWarning(): void
    {
        $this->switch->activate(TenantId::fromString('wholesale'));
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        $this->switch->reset();

        self::assertFalse($connection->isTransactionActive());
        self::assertTrue($this->router->current()->isShared());
        self::assertSame([LogLevel::WARNING, 'A transaction was still open in the database "{database}" when the unit of work left the tenant "{tenant}": it is abandoned with its connection.', ['tenant' => 'wholesale', 'database' => 'tenant_wholesale']], $this->logger->records[1]);
    }

    public function testWithoutDoctrineOrALoggerTheConnectionStillFollows(): void
    {
        $router = new TenantDatabaseRouter();
        $switch = new TenantConnectionSwitch($this->entityManager->getConnection(), $router, new FixedPlacements(['wholesale' => 'tenant_wholesale']));

        $switch->activate(TenantId::fromString('wholesale'));
        self::assertSame('tenant_wholesale', $router->current()->database);

        $switch->deactivate();
        self::assertTrue($router->current()->isShared());
    }

    public function testATransactionLeftOpenIsAbandonedWithoutALogger(): void
    {
        $router = new TenantDatabaseRouter();
        $connection = $this->entityManager->getConnection();
        $switch = new TenantConnectionSwitch($connection, $router, new FixedPlacements(['wholesale' => 'tenant_wholesale']));

        $switch->activate(TenantId::fromString('wholesale'));

        $connection->beginTransaction();
        $switch->deactivate();

        self::assertFalse($connection->isTransactionActive());
        self::assertTrue($router->current()->isShared());
    }

    public function testADatabaseRoutedWithoutATenantIsLeftWithAWarningThatNamesNone(): void
    {
        $connection = $this->entityManager->getConnection();
        $this->router->route(Placement::dedicated('tenant_wholesale'));
        $connection->beginTransaction();

        $this->switch->deactivate();

        self::assertSame([[LogLevel::WARNING, 'A transaction was still open in the database "{database}" when the unit of work left the tenant "{tenant}": it is abandoned with its connection.', ['tenant' => null, 'database' => 'tenant_wholesale']]], $this->logger->records);
    }

    private function managed(EntityManager $entityManager): Note
    {
        $note = new Note();
        $entityManager->persist($note);

        return $note;
    }

    /**
     * @param list<ObjectManager> $managers
     */
    private function registry(array $managers): ManagerRegistry
    {
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn($managers);

        return $registry;
    }
}
