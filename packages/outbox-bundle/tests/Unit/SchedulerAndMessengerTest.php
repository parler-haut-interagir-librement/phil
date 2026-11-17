<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Outbox\Codecs;
use Phil\Outbox\RecordedEvent;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\RelayReport;
use Phil\OutboxBundle\Infrastructure\Messenger\MessengerDispatcher;
use Phil\OutboxBundle\Infrastructure\Scheduler\OutboxSchedule;
use Phil\OutboxBundle\Infrastructure\Scheduler\RelayOutbox;
use Phil\OutboxBundle\Infrastructure\Scheduler\RelayOutboxHandler;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;
use Phil\OutboxBundle\Tests\Unit\Fixture\Outboxes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

#[CoversClass(OutboxSchedule::class)]
#[CoversClass(RelayOutbox::class)]
#[CoversClass(RelayOutboxHandler::class)]
#[CoversClass(MessengerDispatcher::class)]
final class SchedulerAndMessengerTest extends TestCase
{
    public function testTheScheduleSendsARelayMessageEveryFewSeconds(): void
    {
        $messages = new OutboxSchedule(45)->getSchedule()->getRecurringMessages();

        self::assertCount(1, $messages);
        self::assertSame('2026-11-17 10:00:45', $messages[0]->getTrigger()->getNextRunDate(new DateTimeImmutable('2026-11-17 10:00:00'))?->format('Y-m-d H:i:s'));
        self::assertSame('phil_outbox', OutboxSchedule::NAME);
    }

    public function testATickRelaysUntilTheOutboxIsEmptyOrItsBatchesAreSpent(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(5);

        self::assertEquals(new RelayReport(claimed: 4, delivered: 4), new RelayOutboxHandler(new RelayRunner($outboxes->relay, $outboxes->clock), 2, 2)());
        self::assertEquals(new RelayReport(claimed: 1, delivered: 1), new RelayOutboxHandler(new RelayRunner($outboxes->relay, $outboxes->clock), 2, 5)());
        self::assertSame('2026-11-17 10:00:00', $outboxes->clock->now()->format('Y-m-d H:i:s'));
    }

    public function testTheBridgeRelaysTheEventsToTheBus(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(2);

        $middleware = new class implements MiddlewareInterface {
            /** @var list<object> */
            public array $messages = [];

            public bool $down = false;

            public function count(): int
            {
                return \count($this->messages);
            }

            public function first(): ?object
            {
                return $this->messages[0] ?? null;
            }

            #[Override]
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                if ($this->down) {
                    throw new RuntimeException('The transport is down.');
                }

                $this->messages[] = $envelope->getMessage();

                return $stack->next()->handle($envelope, $stack);
            }
        };
        $dispatcher = new MessengerDispatcher(new MessageBus([$middleware]));
        $relay = new Relay($outboxes->store, new Codecs([new RecordedEventCodec(['quiz.completed'])]), $dispatcher, $outboxes->clock);
        $event = new stdClass();

        self::assertSame($event, $dispatcher->dispatch($event));
        $middleware->messages = [];
        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), $relay->relay());
        self::assertSame(2, $middleware->count());
        self::assertInstanceOf(RecordedEvent::class, $middleware->first());

        $outboxes->record(1);
        $middleware->down = true;
        self::assertEquals(new RelayReport(claimed: 1, retried: 1), $relay->relay());
    }
}
