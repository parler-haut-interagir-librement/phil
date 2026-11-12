<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Messenger;

use DateTimeImmutable;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\Messenger\IdempotencyMiddleware;
use Phil\Idempotency\Messenger\IdempotencyStamp;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\Tests\Unit\Fixture\RecordingStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(IdempotencyMiddleware::class)]
#[CoversClass(IdempotencyStamp::class)]
final class IdempotencyMiddlewareTest extends TestCase
{
    private RecordingStore $store;

    private MockClock $clock;

    private int $handled = 0;

    protected function setUp(): void
    {
        $this->store = new RecordingStore();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-11 12:00:00 UTC'));
    }

    public function testAReceivedMessageIsHandledOncePerKey(): void
    {
        $envelope = new Envelope(new stdClass(), [new IdempotencyStamp('order-7', 'orders'), new ReceivedStamp('async')]);

        $first = $this->middleware()->handle($envelope, $this->stack());
        $second = $this->middleware()->handle($envelope, $this->stack());

        self::assertSame(1, $this->handled);
        self::assertInstanceOf(HandledStamp::class, $first->last(HandledStamp::class), 'The handled envelope is returned.');
        self::assertNull($second->last(HandledStamp::class), 'A duplicate is acknowledged without its handlers.');
        self::assertArrayHasKey('orders|order-7', $this->store->records);
        self::assertSame(IdempotencyState::Completed, $this->store->records['orders|order-7']->state);
    }

    public function testTheMessageIdOfMessengerIsTheKeyWithoutAStamp(): void
    {
        $envelope = new Envelope(new stdClass(), [new MessageIdStamp('0193-message'), new ReceivedStamp('async')]);

        $this->middleware()->handle($envelope, $this->stack());
        $this->middleware()->handle($envelope, $this->stack());

        self::assertSame(1, $this->handled);
        self::assertArrayHasKey('messenger|0193-message', $this->store->records);
        self::assertTrue(PayloadFingerprint::ofParts('messenger', stdClass::class)->equals($this->store->records['messenger|0193-message']->fingerprint));
    }

    public function testTheStampWinsOverTheMessageId(): void
    {
        $this->middleware()->handle(new Envelope(new stdClass(), [new MessageIdStamp('m'), new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());

        self::assertSame(['messenger|k'], array_keys($this->store->records));
    }

    public function testADispatchIsNotItsHandling(): void
    {
        $this->middleware()->handle(new Envelope(new stdClass(), [new IdempotencyStamp('k')]), $this->stack());
        $this->middleware()->handle(new Envelope(new stdClass(), [new IdempotencyStamp('k')]), $this->stack());

        self::assertSame(2, $this->handled);
        self::assertSame([], $this->store->calls);
    }

    public function testAnEnvelopeWithoutKeyPassesThrough(): void
    {
        $this->middleware()->handle(new Envelope(new stdClass(), [new ReceivedStamp('async')]), $this->stack());

        self::assertSame(1, $this->handled);
        self::assertSame([], $this->store->calls);
    }

    public function testAMessageHandledElsewhereIsRetriedAfterItsReservation(): void
    {
        $this->store->records['messenger|k'] = IdempotencyRecord::inProgress(PayloadFingerprint::ofParts('messenger', stdClass::class), new DateTimeImmutable('2026-11-11 12:00:42 UTC'));

        try {
            $this->middleware()->handle(new Envelope(new stdClass(), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
            self::fail('A message in progress was handled beside the first.');
        } catch (RecoverableMessageHandlingException $recoverableMessageHandlingException) {
            self::assertSame(42_000, $recoverableMessageHandlingException->getRetryDelay());
            self::assertInstanceOf(RequestInProgress::class, $recoverableMessageHandlingException->getPrevious());
            self::assertSame('A request with this idempotency key is in progress.', $recoverableMessageHandlingException->getMessage());
        }
    }

    public function testAKeyUsedByAnotherMessageClassIsRefusedWithoutRetry(): void
    {
        $this->store->records['messenger|k'] = IdempotencyRecord::inProgress(PayloadFingerprint::ofParts('messenger', 'Other'), new DateTimeImmutable('2026-11-11 12:05:00 UTC'));

        try {
            $this->middleware()->handle(new Envelope(new stdClass(), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
            self::fail('A reused key was handled.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertInstanceOf(IdempotencyKeyReused::class, $unrecoverableMessageHandlingException->getPrevious());
            self::assertSame('This idempotency key was used with another request.', $unrecoverableMessageHandlingException->getMessage());
        }
    }

    public function testAHandlerThatThrowsLeavesTheKeyFailedForTheRetry(): void
    {
        $envelope = new Envelope(new stdClass(), [new IdempotencyStamp('k'), new ReceivedStamp('async')]);

        try {
            $this->middleware()->handle($envelope, $this->stack(new RuntimeException('Down.')));
            self::fail('The failure was swallowed.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Down.', $runtimeException->getMessage());
        }

        $this->middleware()->handle($envelope, $this->stack());

        self::assertSame(2, $this->handled);
    }

    public function testAStampChecksItsKeyAndScopeAtDispatch(): void
    {
        $stamp = new IdempotencyStamp('k');
        self::assertSame(['k', 'messenger'], [$stamp->key, $stamp->scope]);

        $this->expectException(InvalidIdempotencyValue::class);

        new IdempotencyStamp('k', '');
    }

    public function testAStampRefusesAMalformedKey(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        new IdempotencyStamp(' k');
    }

    private function middleware(): IdempotencyMiddleware
    {
        return new IdempotencyMiddleware(new IdempotencyGuard($this->store, $this->clock));
    }

    private function stack(?RuntimeException $failure = null): StackInterface
    {
        $handler = new readonly class($this, $failure) implements MiddlewareInterface {
            public function __construct(
                private IdempotencyMiddlewareTest $test,
                private ?RuntimeException $failure,
            ) {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->test->handledOnce();

                if ($this->failure instanceof RuntimeException) {
                    throw $this->failure;
                }

                return $envelope->with(new HandledStamp(null, 'handler'));
            }
        };

        return new StackMiddleware($handler);
    }

    public function handledOnce(): void
    {
        ++$this->handled;
    }
}
