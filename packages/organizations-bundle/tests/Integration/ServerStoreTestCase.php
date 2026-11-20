<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use LogicException;
use Override;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Domain\ValueObject\InvitationId;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;
use Phil\OrganizationsBundle\Tests\Unit\Infrastructure\StoreTestCase;

/**
 * Every promise of a store on a real server read from the environment, the byte-for-byte comparison of its key columns,
 * and the writes of two connections: the last use of an invitation taken by one is not taken by the other, and an
 * organization locked by one waits for it.
 */
abstract class ServerStoreTestCase extends StoreTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testTheLastUseTakenByOneConnectionIsNotTakenByAnother(): void
    {
        $store = $this->store();
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 5000), '$hash$1');

        $other = $this->connect();
        $other->beginTransaction();
        self::assertTrue(new DbalOrganizationStore($other)->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@2000')));
        $other->commit();
        $other->close();

        self::assertFalse($store->useInvitation(InvitationId::fromString('i1'), new DateTimeImmutable('@2000')));
        self::assertSame(1, $store->invitation(InvitationId::fromString('i1'))?->uses);
    }

    public function testKeyColumnsCompareByteForByte(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));
        $store->saveMembership(self::membership('o1', 'ann', ['owner']));
        $store->saveMembership(self::membership('o1', 'ANN', ['member']));
        $store->addInvitation(self::invitation('i1', 'o1', 1, 1000, 5000), '$hash$abc');

        self::assertSame(['owner'], $store->membership(self::id('o1'), self::member('ann'))?->roles->names);
        self::assertSame(['member'], $store->membership(self::id('o1'), self::member('ANN'))?->roles->names);
        self::assertNull($store->membership(self::id('O1'), self::member('ann')));
        self::assertNull($store->invitationByHash(['$hash$ABC']));
        self::assertSame(1, $store->countWithRole(self::id('o1'), 'owner'));
        self::assertSame(0, $store->countWithRole(self::id('o1'), 'OWNER'));
    }

    public function testALockedOrganizationIsReadForUpdate(): void
    {
        $store = $this->store();
        $store->saveOrganization(self::organization('o1', 'acme'));

        self::assertSame('acme', $store->transactional(static fn (): ?string => $store->organization(self::id('o1'), true)?->slug->value));
    }

    public function testADatabaseOfTheFirstVersionGainsTheTablesOfTheUnits(): void
    {
        $this->store();
        $connection = $this->connection ?? throw new LogicException('No connection.');
        $tables = OrganizationTables::for($connection->getDatabasePlatform());

        foreach ($tables->downgrade() as $statement) {
            $connection->executeStatement($statement);
        }

        $manager = $connection->createSchemaManager();
        self::assertFalse($manager->introspectSchema()->hasTable(OrganizationTables::UNIT));
        $statements = $tables->upgrade($manager->introspectSchema(), $manager->createComparator());
        self::assertNotSame([], $statements);

        foreach ($statements as $statement) {
            self::assertStringNotContainsStringIgnoringCase('DROP', $statement);
            $connection->executeStatement($statement);
        }

        self::assertTrue($manager->introspectSchema()->hasTable(OrganizationTables::UNIT));
        self::assertTrue($manager->introspectSchema()->hasTable(OrganizationTables::UNIT_MEMBER));
        self::assertSame([], $tables->upgrade($manager->introspectSchema(), $manager->createComparator()));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    #[Override]
    protected function store(): OrganizationStore
    {
        $connection = $this->connection ??= $this->connect();
        $store = new DbalOrganizationStore($connection);

        foreach (OrganizationTables::ALL as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
