<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\Outbox\Codecs;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordState;
use Phil\Outbox\StoreOutbox;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\Outbox\EraseModuleCodec;
use Phil\PrivacyBundle\Infrastructure\Outbox\OutboxErasureMessages;
use Symfony\Component\Clock\MockClock;

/**
 * The tables of the bundle in an SQLite database in memory, the store of the erasure requests on them, and an outbox in
 * memory that knows the type of the module messages.
 */
final readonly class ErasureWorkbench
{
    public Connection $connection;

    public DbalErasureRequests $store;

    public InMemoryOutboxStore $records;

    public OutboxErasureMessages $outbox;

    public MockClock $clock;

    public function __construct()
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }

        $this->store = new DbalErasureRequests($this->connection);
        $this->records = new InMemoryOutboxStore();
        $this->clock = new MockClock('2026-11-20 10:00:00', 'UTC');
        $this->outbox = new OutboxErasureMessages(new StoreOutbox($this->records, new Codecs([new EraseModuleCodec($this->store)]), $this->clock));
    }

    /**
     * The pending outbox records, in the order they were added.
     *
     * @return list<OutboxRecord>
     */
    public function pending(): array
    {
        return $this->records->list(RecordState::Pending, 1000);
    }
}
