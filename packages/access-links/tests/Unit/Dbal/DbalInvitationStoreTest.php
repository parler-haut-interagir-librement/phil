<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit\Dbal;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\Tests\Unit\Fixture\RecordingLogger;
use Phil\AccessLinks\Tests\Unit\InvitationStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on SQLite in memory; the same contract runs on MySQL and PostgreSQL in the Integration suite.
 */
#[CoversClass(DbalInvitationStore::class)]
final class DbalInvitationStoreTest extends InvitationStoreTestCase
{
    private ?Connection $connection = null;

    public function testTheSchemaHoldsBothTablesOnce(): void
    {
        $store = new DbalInvitationStore($this->connection(), 'acme_invitation', 'acme_link');
        $schema = $store->configureSchema($this->connection()->createSchemaManager()->introspectSchema());
        $names = static fn (array $columns): array => array_map(static fn (UnqualifiedName $name): string => $name->toString(), $columns);

        self::assertSame(['id'], $names($schema->getTable('acme_invitation')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['hash'], $names($schema->getTable('acme_link')->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(81, $schema->getTable('acme_link')->getColumn('hash')->getLength());
        self::assertSame(191, $schema->getTable('acme_invitation')->getColumn('recipient')->getLength());
        self::assertSame(IndexType::UNIQUE, $schema->getTable('acme_invitation')->getIndex('acme_invitation_recipient')->getType());
        self::assertTrue($schema->getTable('acme_invitation')->hasIndex('acme_invitation_next_reminder'));
        self::assertTrue($schema->getTable('acme_link')->hasIndex('acme_link_invitation_id'));
        self::assertFalse($schema->getTable('acme_invitation')->getColumn('answer')->getNotnull());
        self::assertCount(2, $store->configureSchema($schema)->getTables());
    }

    public function testATableNameIsCheckedBeforeAnySql(): void
    {
        foreach (['', 'Upper', '1table', 'a-b', str_repeat('a', 64), "a\n"] as $name) {
            try {
                new DbalInvitationStore($this->connection(), 'ok', $name);
                self::fail(\sprintf('The table name "%s" was accepted.', $name));
            } catch (InvalidConfiguration $exception) {
                self::assertStringEndsWith('is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $exception->getMessage());
            }
        }

        self::assertInstanceOf(DbalInvitationStore::class, new DbalInvitationStore($this->connection(), '_a', str_repeat('b', 63)));
    }

    public function testAFailureRollsTheInvitationBack(): void
    {
        $connection = $this->connection();
        $store = $this->store();
        $connection->executeStatement('DROP TABLE '.DbalInvitationStore::LINK_TABLE);

        try {
            $store->add(self::invitation('a', 'member-1'), self::hash(1));
            self::fail('An invitation was added without its table of links.');
        } catch (DbalException) {
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertNull($store->get(self::id('a')));
    }

    public function testAFailedLinkRollsTheCountBack(): void
    {
        $connection = $this->connection();
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1'), self::hash(1));

        try {
            $store->addLink(self::id('a'), self::hash(1), 2);
            self::fail('A link was added twice.');
        } catch (DbalException) {
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertTrue($store->addLink(self::id('a'), self::hash(2), 2));
    }

    public function testEveryStatementBindsItsParametersByType(): void
    {
        $logger = new RecordingLogger();
        [$connection, $store] = $this->loggedStore($logger);

        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(1));
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(2));
        $store->addLink(self::id('a'), self::hash(3), 3);
        $store->addLink(self::id('a'), self::hash(4), 2);
        $store->get(self::id('a'));
        $store->findByToken([self::hash(9), self::hash(1)]);
        $store->respond(self::id('a'), 'yes', self::at(10));
        $store->claimReminder(self::id('a'), 0, self::at(300), self::at(100));
        $store->due(self::at(300), 10);
        $store->revoke(self::id('a'), self::at(20));
        $store->counts('poll-1');

        [$string, $integer] = [ParameterType::STRING, ParameterType::INTEGER];
        self::assertSame(
            [[$string, $string, $string, $integer, $integer, $integer, $integer, $integer, $integer, $integer], [$string, $string, $string, $integer, $integer, $integer, $integer, $integer, $integer, $integer]],
            $logger->typesOf('INSERT INTO phil_access_link_invitation '),
        );
        self::assertSame([[$string, $string], [$string, $string]], $logger->typesOf('INSERT INTO phil_access_link '));
        self::assertSame([[$string, $integer], [$string, $integer]], $logger->typesOf('UPDATE phil_access_link_invitation SET links_issued'));
        self::assertSame([[$string], [$string, $string]], $logger->typesOf('SELECT i.id, '));
        self::assertSame([[$string, $integer, $integer, $string, $integer, $integer, $integer]], $logger->typesOf('UPDATE phil_access_link_invitation SET answer'));
        self::assertSame([[$integer, $string, $integer, $integer, $integer]], $logger->typesOf('UPDATE phil_access_link_invitation SET reminders_sent'));
        self::assertSame([[$integer, $integer]], $logger->typesOf('SELECT id FROM'));
        self::assertSame([[$integer, $string]], $logger->typesOf('UPDATE phil_access_link_invitation SET revoked_at'));
        self::assertSame([[$string]], $logger->typesOf('SELECT COUNT'));
        $connection->close();
    }

    public function testAnInvitationWithoutReminderBindsNullForIt(): void
    {
        $logger = new RecordingLogger();
        [$connection, $store] = $this->loggedStore($logger);

        $store->add(self::invitation('a', 'member-1'), self::hash(1));
        $store->claimReminder(self::id('a'), 0, null, self::at(100));

        self::assertSame(ParameterType::NULL, $logger->typesOf('INSERT INTO phil_access_link_invitation ')[0][8]);
        self::assertSame(ParameterType::NULL, $logger->typesOf('UPDATE phil_access_link_invitation SET reminders_sent')[0][0]);
        $connection->close();
    }

    public function testEveryWriteEndsItsTransaction(): void
    {
        $logger = new RecordingLogger();
        [$connection, $store] = $this->loggedStore($logger);

        $store->add(self::invitation('a', 'member-1'), self::hash(1));
        $store->add(self::invitation('a', 'member-1'), self::hash(2));
        $store->addLink(self::id('a'), self::hash(3), 2);
        $store->addLink(self::id('a'), self::hash(4), 2);

        self::assertSame(
            ['Beginning transaction', 'Committing transaction', 'Beginning transaction', 'Rolling back transaction', 'Beginning transaction', 'Committing transaction', 'Beginning transaction', 'Rolling back transaction'],
            $logger->steps,
        );
        self::assertFalse($connection->isTransactionActive());
        $connection->close();
    }

    public function testTheColumnsHaveTheirLengthsAndDefaults(): void
    {
        $schema = new DbalInvitationStore($this->connection())->configureSchema($this->connection()->createSchemaManager()->introspectSchema());
        $invitation = $schema->getTable(DbalInvitationStore::INVITATION_TABLE);

        self::assertSame(32, $invitation->getColumn('id')->getLength());
        self::assertSame(100, $invitation->getColumn('campaign')->getLength());
        self::assertSame(0, $invitation->getColumn('reminders_sent')->getDefault());
        self::assertSame(1, $invitation->getColumn('links_issued')->getDefault());
        self::assertSame(32, $schema->getTable(DbalInvitationStore::LINK_TABLE)->getColumn('invitation_id')->getLength());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    #[Override]
    protected function store(): InvitationStore
    {
        $connection = $this->connection();
        $store = new DbalInvitationStore($connection);

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    /**
     * @return array{Connection, DbalInvitationStore}
     */
    private function loggedStore(RecordingLogger $logger): array
    {
        $configuration = new Configuration();
        $configuration->setMiddlewares([new LoggingMiddleware($logger)]);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $store = new DbalInvitationStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return [$connection, $store];
    }

    private function connection(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
