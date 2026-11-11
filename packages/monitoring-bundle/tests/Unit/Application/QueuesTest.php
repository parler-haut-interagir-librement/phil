<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Application;

use DateTimeImmutable;
use LogicException;
use Phil\MonitoringBundle\Application\Queue\FailedMessage;
use Phil\MonitoringBundle\Application\Queue\Queues;
use Phil\MonitoringBundle\Application\Queue\QueueSummary;
use Phil\MonitoringBundle\Tests\Unit\Support\FakeReceiver;
use Phil\MonitoringBundle\Tests\Unit\Support\PlainReceiver;
use Phil\MonitoringBundle\Tests\Unit\Support\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

#[CoversClass(Queues::class)]
#[CoversClass(FailedMessage::class)]
#[CoversClass(QueueSummary::class)]
final class QueuesTest extends TestCase
{
    private FakeReceiver $failed;

    private FakeReceiver $async;

    private RecordingBus $bus;

    private Queues $queues;

    private Envelope $failure;

    protected function setUp(): void
    {
        $this->failure = new Envelope(new stdClass(), [
            new TransportMessageIdStamp(7),
            new SentToFailureTransportStamp('async'),
            new RedeliveryStamp(1, new DateTimeImmutable('2026-11-08 09:00:00 UTC')),
            new RedeliveryStamp(2, new DateTimeImmutable('2026-11-08 09:30:00 UTC')),
            new ErrorDetailsStamp(LogicException::class, 0, 'Jane Doe <jane@example.com> broke it'),
        ]);
        $this->failed = new FakeReceiver([$this->failure, new Envelope(new stdClass()), new Envelope(new stdClass(), [new TransportMessageIdStamp('x9')])]);
        $this->async = new FakeReceiver([new Envelope(new stdClass(), [new TransportMessageIdStamp('a1')])]);
        $this->bus = new RecordingBus();
        $receivers = new ServiceLocator([
            'async' => fn (): ReceiverInterface => $this->async,
            'failed' => fn (): ReceiverInterface => $this->failed,
            'sync' => static fn (): ReceiverInterface => new PlainReceiver(),
            'odd' => static fn (): object => new stdClass(),
        ]);
        $this->queues = new Queues($receivers, ['async', 'failed', 'sync', 'odd', 'missing'], ['failed', 'odd'], $this->bus);
    }

    public function testTheOverviewCountsWhatCanBeCounted(): void
    {
        self::assertEquals([
            new QueueSummary('async', false, 1),
            new QueueSummary('failed', true, 3),
            new QueueSummary('sync', false, null),
            new QueueSummary('odd', true, null),
            new QueueSummary('missing', false, null),
        ], $this->queues->overview());
        self::assertSame(['failed', 'odd'], $this->queues->failureTransports());
    }

    public function testTheFailedMessagesOfAFailureTransportWithoutTheirContent(): void
    {
        self::assertEquals([
            new FailedMessage('7', stdClass::class, LogicException::class, 'async', 2, new DateTimeImmutable('2026-11-08 09:30:00 UTC')),
            new FailedMessage('x9', stdClass::class, null, null, 0, null),
        ], $this->queues->failed('failed', 50));
        self::assertSame([50], $this->failed->limits);
        self::assertSame([], $this->queues->failed('async', 50));
        self::assertSame([], $this->queues->failed('odd', 50));
        self::assertSame([], $this->async->limits);

        $this->queues->failed('failed', 0);
        self::assertSame([50, 1], $this->failed->limits);
    }

    public function testAMessageWithoutStampsIsListedBare(): void
    {
        self::assertEquals(new FailedMessage('x', stdClass::class, null, null, 0, null), FailedMessage::fromEnvelope(new Envelope(new stdClass(), [new TransportMessageIdStamp('x')])));
        self::assertNull(FailedMessage::fromEnvelope(new Envelope(new stdClass())));
    }

    public function testARetriedMessageGoesBackToItsTransportAndLeavesTheFailureTransport(): void
    {
        self::assertTrue($this->queues->retry('failed', '7'));
        self::assertCount(1, $this->bus->dispatched);
        self::assertSame($this->failure->getMessage(), $this->bus->dispatched[0]->getMessage());
        self::assertSame(['async'], $this->bus->dispatched[0]->last(TransportNamesStamp::class)?->getTransportNames());
        self::assertSame([$this->failure], $this->failed->rejected);
    }

    public function testAMessageWithoutItsTransportIsRoutedAgain(): void
    {
        $envelope = new Envelope(new stdClass(), [new TransportMessageIdStamp(8)]);
        $this->failed->envelopes = [$envelope];

        self::assertTrue($this->queues->retry('failed', '8'));
        self::assertSame([], $this->bus->dispatched[0]->all());
    }

    public function testARemovedMessageLeavesTheFailureTransport(): void
    {
        self::assertTrue($this->queues->remove('failed', '7'));
        self::assertSame([$this->failure], $this->failed->rejected);
        self::assertSame([], $this->bus->dispatched);
    }

    public function testOnlyAFailedMessageOfAFailureTransportIsRetriedOrRemoved(): void
    {
        self::assertFalse($this->queues->retry('failed', '9'));
        self::assertFalse($this->queues->remove('failed', '9'));
        self::assertFalse($this->queues->retry('async', 'a1'));
        self::assertFalse($this->queues->remove('async', 'a1'));
        self::assertFalse($this->queues->remove('odd', '7'));
        self::assertTrue($this->queues->isFailureTransport('failed'));
        self::assertFalse($this->queues->isFailureTransport('async'));
        self::assertSame([], $this->failed->rejected);
        self::assertSame([], $this->async->rejected);
        self::assertSame([], $this->bus->dispatched);
    }
}
