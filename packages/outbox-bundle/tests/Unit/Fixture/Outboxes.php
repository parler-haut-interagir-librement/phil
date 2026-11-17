<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit\Fixture;

use Phil\Outbox\Codecs;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\RecordedEvent;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\StoreOutbox;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * An outbox in memory with its relay, its housekeeping and a dispatcher whose listener keeps what it receives.
 */
final class Outboxes
{
    public readonly InMemoryOutboxStore $store;

    public readonly MockClock $clock;

    public readonly StoreOutbox $outbox;

    public readonly Relay $relay;

    public readonly Housekeeping $housekeeping;

    public readonly EventDispatcher $dispatcher;

    /** @var list<RecordedEvent> */
    public array $received = [];

    public function __construct(int $lease = 60)
    {
        $this->store = new InMemoryOutboxStore();
        $this->clock = new MockClock('2026-11-17 10:00:00', 'UTC');
        $codecs = new Codecs([new RecordedEventCodec(['quiz.completed'])]);
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(RecordedEvent::class, function (RecordedEvent $event): void {
            $this->received[] = $event;
        });
        $this->outbox = new StoreOutbox($this->store, $codecs, $this->clock);
        $this->relay = new Relay($this->store, $codecs, $this->dispatcher, $this->clock, lease: $lease);
        $this->housekeeping = new Housekeeping($this->store, $this->clock);
    }

    /**
     * How many events the listener received.
     */
    public function receivedCount(): int
    {
        return \count($this->received);
    }

    /**
     * Records some events, each of its own quiz.
     */
    public function record(int $count): void
    {
        for ($index = 0; $index < $count; ++$index) {
            $this->outbox->record('quiz.completed', ['quiz' => 'q'.$index]);
        }
    }
}
