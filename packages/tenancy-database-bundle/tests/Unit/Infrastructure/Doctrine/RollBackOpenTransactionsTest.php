<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\RollBackOpenTransactions;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\RefusedRollback;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\WatchingActivation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[CoversClass(RollBackOpenTransactions::class)]
final class RollBackOpenTransactionsTest extends TestCase
{
    private const string WARNING = 'A transaction was still open at the end of the unit of work of the tenant "{tenant}": it is rolled back.';

    private Connection $connection;

    private Connection $store;

    private WatchingActivation $activation;

    private TenantContext $context;

    private RecordingLogger $logger;

    private RollBackOpenTransactions $rollback;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = $this->sqlite();
        $this->store = $this->sqlite();
        $this->activation = new WatchingActivation($this->connection);
        $this->context = new TenantContext([$this->activation]);
        $this->logger = new RecordingLogger();
        $this->rollback = new RollBackOpenTransactions([$this->connection, $this->store], $this->context, $this->logger);
    }

    public function testAUnitOfWorkWithoutATransactionOnlyLeavesTheTenant(): void
    {
        $unopened = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->context->enter(TenantId::fromString('retail'));

        new RollBackOpenTransactions([$this->connection, $unopened], $this->context, $this->logger)->endUnitOfWork(new stdClass());

        self::assertSame([], $this->logger->records);
        self::assertNull($this->context->current());
        self::assertSame([0], $this->activation->left);
        self::assertTrue($this->connection->isConnected());
        self::assertFalse($unopened->isConnected(), 'A connection never opened stays closed.');
    }

    public function testAnOpenTransactionIsRolledBackBeforeTheTenantIsLeft(): void
    {
        $this->context->enter(TenantId::fromString('retail'));
        $this->connection->beginTransaction();
        $this->connection->executeStatement("INSERT INTO note (text) VALUES ('kept back')");

        $this->rollback->endUnitOfWork(new stdClass());

        self::assertSame([[LogLevel::WARNING, self::WARNING, ['tenant' => 'retail']]], $this->logger->records);
        self::assertSame([0], $this->activation->left, 'Rolled back before the tenant is left.');
        self::assertFalse($this->connection->isTransactionActive());
        self::assertTrue($this->connection->isConnected(), 'Rolled back on the same connection, not closed.');
        self::assertSame(0, $this->notes($this->connection));
    }

    public function testEveryLevelOfANestedTransactionIsRolledBack(): void
    {
        $this->connection->beginTransaction();
        $this->connection->executeStatement("INSERT INTO note (text) VALUES ('outer')");
        $this->connection->beginTransaction();
        $this->connection->executeStatement("INSERT INTO note (text) VALUES ('inner')");

        $this->rollback->endUnitOfWork(new stdClass());

        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        self::assertTrue($this->connection->isConnected());
        self::assertSame(0, $this->notes($this->connection));
        self::assertSame([[LogLevel::WARNING, self::WARNING, ['tenant' => null]]], $this->logger->records, 'One warning for the connection, whatever its levels.');
    }

    public function testEachConnectionIsRolledBack(): void
    {
        $this->connection->beginTransaction();
        $this->store->beginTransaction();
        $this->store->executeStatement("INSERT INTO note (text) VALUES ('store')");

        $this->rollback->endUnitOfWork(new stdClass());

        self::assertFalse($this->connection->isTransactionActive());
        self::assertFalse($this->store->isTransactionActive());
        self::assertSame(0, $this->notes($this->store));
        self::assertCount(2, $this->logger->records);
    }

    public function testAConnectionWithoutAutocommitIsRolledBackOnce(): void
    {
        $configuration = new Configuration();
        $configuration->setAutoCommit(false);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $connection->executeStatement('CREATE TABLE note (text VARCHAR(255))');
        $connection->commit();
        $connection->executeStatement("INSERT INTO note (text) VALUES ('uncommitted')");

        new RollBackOpenTransactions([$connection], $this->context, $this->logger)->endUnitOfWork(new stdClass());

        self::assertSame(1, $connection->getTransactionNestingLevel(), 'The transaction a connection without autocommit begins again.');
        self::assertSame(0, $this->notes($connection));
        self::assertCount(1, $this->logger->records);
    }

    public function testAConnectionThatCannotRollBackIsClosed(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => RefusedRollback::class]);
        self::assertInstanceOf(RefusedRollback::class, $connection);
        $connection->beginTransaction();
        $connection->beginTransaction();

        new RollBackOpenTransactions([$connection, $this->store], $this->context, $this->logger)->endUnitOfWork(new stdClass());

        self::assertSame(1, $connection->attempts, 'Closed at the first refusal.');
        self::assertFalse($connection->isConnected());
        self::assertFalse($connection->isTransactionActive());
        self::assertCount(1, $this->logger->records);
        self::assertSame([0], $this->activation->left, 'The tenant is left all the same.');
    }

    public function testAClosedConnectionIsLeftAsItIs(): void
    {
        $this->connection->beginTransaction();
        $this->connection->close();

        $this->rollback->endUnitOfWork(new stdClass());

        self::assertFalse($this->connection->isConnected());
        self::assertSame([], $this->logger->records);
    }

    public function testTheTenantOfAMessageIsReadFromItsStampOnceTheTenantIsLeft(): void
    {
        $this->connection->beginTransaction();
        $envelope = new Envelope(new stdClass(), [new TenantStamp('wholesale')]);

        $this->rollback->endUnitOfWork(new WorkerMessageHandledEvent($envelope, 'async'));
        $this->connection->beginTransaction();
        $this->rollback->endUnitOfWork(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('Failed.')));
        $this->connection->beginTransaction();
        $this->rollback->endUnitOfWork(new WorkerMessageHandledEvent(new Envelope(new stdClass()), 'async'));

        self::assertSame([
            [LogLevel::WARNING, self::WARNING, ['tenant' => 'wholesale']],
            [LogLevel::WARNING, self::WARNING, ['tenant' => 'wholesale']],
            [LogLevel::WARNING, self::WARNING, ['tenant' => null]],
        ], $this->logger->records);
    }

    public function testTheActiveTenantComesBeforeTheStampOfAMessage(): void
    {
        $this->context->enter(TenantId::fromString('retail'));
        $this->connection->beginTransaction();

        $this->rollback->endUnitOfWork(new WorkerMessageHandledEvent(new Envelope(new stdClass(), [new TenantStamp('wholesale')]), 'async'));

        self::assertSame([[LogLevel::WARNING, self::WARNING, ['tenant' => 'retail']]], $this->logger->records);
    }

    public function testNoLoggerIsNeeded(): void
    {
        $this->connection->beginTransaction();

        new RollBackOpenTransactions([$this->connection], $this->context)->endUnitOfWork(new stdClass());

        self::assertFalse($this->connection->isTransactionActive());
    }

    private function notes(Connection $connection): int
    {
        $count = $connection->fetchOne('SELECT COUNT(*) FROM note');
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE note (text VARCHAR(255))');

        return $connection;
    }
}
