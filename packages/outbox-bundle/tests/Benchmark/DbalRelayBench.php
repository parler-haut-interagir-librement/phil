<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Benchmark;

use Doctrine\DBAL\Connection;
use Phil\Outbox\Codecs;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\StoreOutbox;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Tests\Unit\Fixture\Databases;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * What the hot path costs on a database: a hundred events recorded in one transaction, half of them on ordering keys,
 * then relayed in one batch of the DBAL store — the claim, the hundred dispatches and their marks. SQLite in memory
 * keeps the network out of the measure.
 */
final readonly class DbalRelayBench
{
    private Connection $connection;

    private DbalOutboxStore $store;

    private Codecs $codecs;

    private MockClock $clock;

    public function __construct()
    {
        $this->connection = Databases::sqlite();
        $this->store = new DbalOutboxStore($this->connection);
        $this->codecs = new Codecs([new RecordedEventCodec(['quiz.completed'])]);
        $this->clock = new MockClock('2026-11-17 10:00:00', 'UTC');
    }

    public function benchRecordAndRelayAHundredEvents(): void
    {
        $outbox = new StoreOutbox($this->store, $this->codecs, $this->clock);
        $this->connection->transactional(static function () use ($outbox): void {
            for ($index = 0; $index < 100; ++$index) {
                $outbox->record('quiz.completed', ['quiz' => 'q'.$index, 'member' => $index], 0 === $index % 2 ? 'quiz:q'.$index : null);
            }
        });

        new Relay($this->store, $this->codecs, new EventDispatcher(), $this->clock)->relay(100);
        $this->connection->executeStatement('DELETE FROM phil_outbox');
    }
}
