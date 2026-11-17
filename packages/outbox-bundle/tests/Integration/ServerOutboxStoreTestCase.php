<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Test\OutboxStoreTestCase;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Tests\Unit\Fixture\Databases;
use RuntimeException;

/**
 * The contract of a store on a real server, read from the environment, and what only a server shows: a record that
 * joins the transaction of the caller, an idempotency key kept without aborting that transaction, and keys compared
 * byte by byte.
 */
abstract class ServerOutboxStoreTestCase extends OutboxStoreTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->connection instanceof Connection) {
            Databases::drop($this->connection);
            $this->connection->close();
        }
    }

    public function testARecordJoinsTheTransactionOfTheCaller(): void
    {
        $store = $this->createStore();
        $record = self::record('k1');

        try {
            $this->connection()->transactional(static function () use ($store, $record): void {
                $store->add($record);

                throw new RuntimeException('The write of the package failed.');
            });
        } catch (RuntimeException) {
        }

        self::assertNull($store->find($record->id));
    }

    public function testAKeptIdempotencyKeyLeavesTheTransactionOfTheCallerAlive(): void
    {
        $store = $this->createStore();
        $first = self::record('k1');
        $store->add($first);

        $kept = $this->connection()->transactional(static function () use ($store): string {
            $id = $store->add(self::record('k1'))->value;
            $store->add(self::record('k2'));

            return $id;
        });

        self::assertSame($first->id->value, $kept);
        self::assertSame(2, $store->count(RecordState::Pending));
    }

    public function testTheKeysCompareByteByByte(): void
    {
        $store = $this->createStore();
        $upper = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), 'set:A', 'K', self::at('10:00:00'));
        $lower = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), 'set:a', 'k', self::at('10:00:00'));
        $store->add($upper);

        self::assertSame($lower->id->value, $store->add($lower)->value);
        self::assertSame([$upper->id->value, $lower->id->value], self::ids($store->claim('cccccccccccccccccccccccccccccccc', 10, self::at('10:00:00'), self::at('10:01:00'))));
    }

    #[Override]
    protected function createStore(): OutboxStore
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        Databases::drop($this->connection);
        Databases::create($this->connection);

        return new DbalOutboxStore($this->connection);
    }

    private function connection(): Connection
    {
        return $this->connection ?? throw new RuntimeException('The store is not created.');
    }

    private static function record(string $idempotencyKey): OutboxRecord
    {
        return OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of(['n' => 1]), null, $idempotencyKey, new DateTimeImmutable('2026-11-17 10:00:00', new DateTimeZone('UTC')));
    }
}
