<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Benchmark;

use Phil\Outbox\Codecs;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\StoreOutbox;
use Phil\Outbox\Tests\Unit\Fixture\RecordingDispatcher;
use Symfony\Component\Clock\MockClock;

/**
 * What the hot path costs: recording a hundred events, half of them on ordering keys, then relaying them in one batch,
 * each rebuilt, dispatched and marked delivered. The store is in memory, so that the relay's own work is measured.
 */
final readonly class RelayBench
{
    private Codecs $codecs;

    private MockClock $clock;

    public function __construct()
    {
        $this->codecs = new Codecs([new RecordedEventCodec(['quiz.completed'])]);
        $this->clock = new MockClock('2026-11-17 10:00:00', 'UTC');
    }

    public function benchRecordAHundredEvents(): void
    {
        $this->record(new InMemoryOutboxStore());
    }

    public function benchRecordAndRelayAHundredEvents(): void
    {
        $store = new InMemoryOutboxStore();
        $this->record($store);
        new Relay($store, $this->codecs, new RecordingDispatcher(), $this->clock)->relay(100);
    }

    private function record(InMemoryOutboxStore $store): void
    {
        $outbox = new StoreOutbox($store, $this->codecs, $this->clock);

        for ($index = 0; $index < 100; ++$index) {
            $outbox->record('quiz.completed', ['quiz' => 'q'.$index, 'member' => $index], 0 === $index % 2 ? 'quiz:q'.$index : null);
        }
    }
}
