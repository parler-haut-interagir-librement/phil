<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit\Dbal;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use Phil\AccessCodes\Tests\Unit\CodeStoreTestCase;
use Phil\AccessCodes\Tests\Unit\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use UnexpectedValueException;

/**
 * The DBAL store on SQLite in memory; the same contract runs on MySQL and PostgreSQL in the Integration suite.
 */
#[CoversClass(DbalCodeStore::class)]
final class DbalCodeStoreTest extends CodeStoreTestCase
{
    private ?Connection $connection = null;

    public function testTheSchemaHoldsBothTablesOnce(): void
    {
        $store = new DbalCodeStore($this->connection(), 'acme_batch', 'acme_code');
        $schema = $store->configureSchema($this->connection()->createSchemaManager()->introspectSchema());

        self::assertTrue($schema->hasTable('acme_batch'));
        self::assertTrue($schema->hasTable('acme_code'));
        self::assertSame(['id'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $schema->getTable('acme_batch')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['hash'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $schema->getTable('acme_code')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(81, $schema->getTable('acme_code')->getColumn('hash')->getLength());
        self::assertSame(120, $schema->getTable('acme_batch')->getColumn('name')->getLength());
        self::assertSame(64, $schema->getTable('acme_batch')->getColumn('id')->getLength());
        self::assertSame(64, $schema->getTable('acme_code')->getColumn('batch_id')->getLength());
        self::assertSame(191, $schema->getTable('acme_code')->getColumn('redeemed_by')->getLength());
        self::assertFalse($schema->getTable('acme_code')->getColumn('redeemed_by')->getNotnull());
        self::assertTrue($schema->getTable('acme_code')->hasIndex('acme_code_batch_id'));
        self::assertCount(2, $store->configureSchema($schema)->getTables());
    }

    public function testATableNameIsCheckedBeforeAnySql(): void
    {
        foreach (['', 'Upper', '1table', 'a-b', str_repeat('a', 64), "a\n"] as $name) {
            try {
                new DbalCodeStore($this->connection(), $name);
                self::fail(\sprintf('The table name "%s" was accepted.', $name));
            } catch (InvalidConfiguration $exception) {
                self::assertStringEndsWith('is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $exception->getMessage());
            }
        }

        self::assertInstanceOf(DbalCodeStore::class, new DbalCodeStore($this->connection(), '_a', str_repeat('b', 63)));
    }

    public function testAFailureRollsTheBatchBack(): void
    {
        $connection = $this->connection();
        $store = $this->store();
        $connection->executeStatement('DROP TABLE '.DbalCodeStore::CODE_TABLE);

        try {
            $store->add($this->batch('b1', 1), [self::hash(1)]);
            self::fail('A batch was added without its table of codes.');
        } catch (DbalException) {
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertNull($store->batch('b1'));
    }

    public function testAFailedRevocationRollsBack(): void
    {
        $connection = $this->connection();
        $store = $this->store();
        $store->add($this->batch('b1', 1), [self::hash(1)]);

        $connection->executeStatement('DROP TABLE '.DbalCodeStore::CODE_TABLE);

        try {
            $store->revoke('b1', self::at(1));
            self::fail('A batch was revoked without its table of codes.');
        } catch (DbalException) {
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertNull($store->batch('b1')?->revokedAt);
    }

    public function testEveryStatementBindsItsParametersByType(): void
    {
        $logger = new RecordingLogger();
        $configuration = new Configuration();
        $configuration->setMiddlewares([new LoggingMiddleware($logger)]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $store = new DbalCodeStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $store->add($this->batch('b1', 2, '+1 day'), [self::hash(1), self::hash(2)]);
        $store->redeem([self::hash(9), self::hash(1)], 'member-7', self::at(10));
        $store->find([self::hash(9), self::hash(1)]);
        $store->batch('b1');
        $store->counts('b1');
        $store->revoke('b1', self::at(20));
        $store->revoke('b9', self::at(20));

        [$string, $integer, $null] = [ParameterType::STRING, ParameterType::INTEGER, ParameterType::NULL];
        self::assertSame([[$string, $string, $integer, $integer, $integer]], $logger->typesOf('INSERT INTO phil_access_code_batch'));
        self::assertSame([[$string, $string, $integer, $string, $string, $integer]], $logger->typesOf('INSERT INTO phil_access_code '));
        self::assertSame([[$integer, $string, $string, $string, $integer]], $logger->typesOf('UPDATE phil_access_code SET redeemed_at'));
        self::assertSame([[$string, $string], [$string, $string]], $logger->typesOf('SELECT batch_id'));
        self::assertSame([[$string]], $logger->typesOf('SELECT id, name'));
        self::assertSame([[$string]], $logger->typesOf('SELECT COUNT'));
        self::assertSame([[$integer, $string], [$integer, $string]], $logger->typesOf('UPDATE phil_access_code_batch SET revoked_at'));
        self::assertSame([[$integer, $string]], $logger->typesOf('UPDATE phil_access_code SET revoked_at'));
        self::assertSame(
            ['Beginning transaction', 'Committing transaction', 'Beginning transaction', 'Committing transaction', 'Beginning transaction', 'Rolling back transaction'],
            $logger->steps,
        );
        self::assertNotContains($null, array_merge(...$logger->typesOf('INSERT INTO')));
        $connection->close();
    }

    public function testABatchWithoutExpiryBindsNullsForIt(): void
    {
        $logger = new RecordingLogger();
        $configuration = new Configuration();
        $configuration->setMiddlewares([new LoggingMiddleware($logger)]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $store = new DbalCodeStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $store->add($this->batch('b1', 1), [self::hash(1)]);

        self::assertSame([[ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::NULL]], $logger->typesOf('INSERT INTO phil_access_code_batch'));
        self::assertSame([[ParameterType::STRING, ParameterType::STRING, ParameterType::NULL]], $logger->typesOf('INSERT INTO phil_access_code '));
        $connection->close();
    }

    public function testABatchWithoutCreationInstantIsRefused(): void
    {
        $connection = $this->connection();
        $store = new DbalCodeStore($connection);
        $connection->executeStatement('CREATE TABLE phil_access_code_batch (id VARCHAR(64) PRIMARY KEY, name VARCHAR(120), size INTEGER, created_at BIGINT NULL, expires_at BIGINT NULL, revoked_at BIGINT NULL)');
        $connection->executeStatement("INSERT INTO phil_access_code_batch (id, name, size) VALUES ('b1', 'Cards', 1)");

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('A batch has no creation instant.');

        $store->batch('b1');
    }

    public function testNoTransactionStaysOpenAfterAWrite(): void
    {
        $connection = $this->connection();
        $store = $this->store();

        $store->add($this->batch('b1', 1), [self::hash(1)]);
        self::assertFalse($connection->isTransactionActive());

        self::assertSame(1, $store->revoke('b1', self::at(1)));
        self::assertFalse($connection->isTransactionActive());

        self::assertSame(0, $store->revoke('b1', self::at(2)));
        self::assertFalse($connection->isTransactionActive());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    #[Override]
    protected function store(): CodeStore
    {
        $connection = $this->connection();
        $store = new DbalCodeStore($connection);

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connection(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
