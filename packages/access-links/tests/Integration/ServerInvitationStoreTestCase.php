<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use Phil\AccessLinks\Tests\Unit\InvitationStoreTestCase;

/**
 * Every promise of a store, on a real server read from the environment, and the writes of two connections: a reminder
 * claimed by one is not claimed by the other, an answer recorded by one is seen by the other.
 */
abstract class ServerInvitationStoreTestCase extends InvitationStoreTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testAReminderClaimedByOneConnectionIsNotClaimedByAnother(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(1));

        $other = $this->connect();
        $other->beginTransaction();
        self::assertTrue(new DbalInvitationStore($other)->claimReminder(self::id('a'), 0, null, self::at(100)));
        $other->commit();
        $other->close();

        self::assertFalse($store->claimReminder(self::id('a'), 0, null, self::at(100)));
        self::assertSame(1, $store->get(self::id('a'))?->remindersSent);
    }

    public function testAnAnswerOfAnotherConnectionStopsTheReminders(): void
    {
        $store = $this->store();
        $store->add(self::invitation('a', 'member-1', nextReminderAt: 100), self::hash(1));

        $other = $this->connect();
        self::assertTrue(new DbalInvitationStore($other)->respond(self::id('a'), 'oui, évidemment', self::at(50)));
        $other->close();

        self::assertSame('oui, évidemment', $store->get(self::id('a'))?->answer);
        self::assertSame([], $store->due(self::at(100), 10));
        self::assertFalse($store->claimReminder(self::id('a'), 0, null, self::at(100)));
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
        $connection = $this->connection ??= $this->connect();
        $store = new DbalInvitationStore($connection);

        foreach ([DbalInvitationStore::LINK_TABLE, DbalInvitationStore::INVITATION_TABLE] as $table) {
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
