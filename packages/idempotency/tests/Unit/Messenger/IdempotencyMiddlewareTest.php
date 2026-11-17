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
use Phil\Idempotency\Tests\Unit\Fixture\Refund;
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
        self::assertTrue(PayloadFingerprint::ofParts('messenger', stdClass::class, '{"stdClass":[]}')->equals($this->store->records['messenger|0193-message']->fingerprint));
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
        $this->store->records['messenger|k'] = IdempotencyRecord::inProgress(PayloadFingerprint::ofParts('messenger', stdClass::class, '{"stdClass":[]}'), new DateTimeImmutable('2026-11-11 12:00:42 UTC'));

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

    public function testAKeyReusedWithAnotherContentIsRefusedWithoutRetry(): void
    {
        $this->middleware()->handle(new Envelope($this->order('order-1', 100), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
        $this->middleware()->handle(new Envelope($this->order('order-1', 100), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
        self::assertSame(1, $this->handled, 'The same content under the same key is one intention.');

        try {
            $this->middleware()->handle(new Envelope($this->order('order-2', 100), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
            self::fail('A key reused with another content was acknowledged.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertInstanceOf(IdempotencyKeyReused::class, $unrecoverableMessageHandlingException->getPrevious());
        }

        self::assertSame(1, $this->handled);
        self::assertTrue(PayloadFingerprint::ofParts('messenger', stdClass::class, '{"stdClass":{"order":"order-1","amount":100}}')->equals($this->store->records['messenger|k']->fingerprint));
    }

    public function testWithoutComparingTheContentTheClassIsTheFingerprint(): void
    {
        $middleware = new IdempotencyMiddleware(new IdempotencyGuard($this->store, $this->clock), compareContent: false);
        $middleware->handle(new Envelope($this->order('order-1', 100), [new IdempotencyStamp('k', fingerprint: PayloadFingerprint::ofParts('a')), new ReceivedStamp('async')]), $this->stack());
        $middleware->handle(new Envelope($this->order('order-2', 5), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());

        self::assertSame(1, $this->handled, 'The second message is acknowledged as a duplicate.');
        self::assertTrue(PayloadFingerprint::ofParts('messenger', stdClass::class)->equals($this->store->records['messenger|k']->fingerprint));
    }

    public function testTheMessageNamesTheFieldsOfItsIntention(): void
    {
        $this->middleware()->handle(new Envelope(new Refund('order-1', 'first try'), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
        $this->middleware()->handle(new Envelope(new Refund('order-1', 'second try'), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());

        self::assertSame(1, $this->handled, 'The note is not part of the intention.');
        self::assertTrue(PayloadFingerprint::ofParts('messenger', Refund::class, PayloadFingerprint::ofParts('order-1')->hex)->equals($this->store->records['messenger|k']->fingerprint));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->middleware()->handle(new Envelope(new Refund('order-2', 'first try'), [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
    }

    public function testTheStampFingerprintWinsOverTheMessage(): void
    {
        $given = PayloadFingerprint::ofParts('intention-7');
        $this->middleware()->handle(new Envelope(new Refund('order-1', ''), [new IdempotencyStamp('k', fingerprint: $given), new ReceivedStamp('async')]), $this->stack());
        $this->middleware()->handle(new Envelope(new Refund('order-2', ''), [new IdempotencyStamp('k', fingerprint: $given), new ReceivedStamp('async')]), $this->stack());

        self::assertSame(1, $this->handled);
        self::assertTrue(PayloadFingerprint::ofParts('messenger', Refund::class, $given->hex)->equals($this->store->records['messenger|k']->fingerprint));
    }

    public function testAMessageWithAClosureNeedsAFingerprint(): void
    {
        $message = new stdClass();
        $message->callback = static fn (): null => null;

        try {
            $this->middleware()->handle(new Envelope($message, [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
            self::fail('A message without a fingerprint was handled.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertSame('The content of the message stdClass cannot be read for its idempotency fingerprint: implement Phil\Idempotency\Contract\FingerprintedMessage, or give the IdempotencyStamp a fingerprint.', $unrecoverableMessageHandlingException->getMessage());
            $previous = $unrecoverableMessageHandlingException->getPrevious();
            self::assertInstanceOf(InvalidIdempotencyValue::class, $previous);
            self::assertSame('A closure or a resource has no content to compare.', $previous->getMessage());
        }

        self::assertSame(0, $this->handled);
        self::assertSame([], $this->store->calls);
    }

    public function testAMessageWithAResourceNeedsAFingerprint(): void
    {
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);
        $message = new stdClass();
        $message->stream = $stream;

        try {
            $this->middleware()->handle(new Envelope($message, [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
            self::fail('A message without a fingerprint was handled.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            $previous = $unrecoverableMessageHandlingException->getPrevious();
            self::assertInstanceOf(InvalidIdempotencyValue::class, $previous);
            self::assertSame('A closure or a resource has no content to compare.', $previous->getMessage());
        } finally {
            fclose($stream);
        }

        self::assertSame(0, $this->handled);
    }

    public function testTheContentCoversPrivatePropertiesNestedObjectsAndFloats(): void
    {
        $nested = new stdClass();
        $nested->total = 1.0;
        $nested->lines = [new DateTimeImmutable('2026-11-17 12:00:00 UTC'), 'a/b'];
        $this->middleware()->handle(new Envelope($nested, [new MessageIdStamp('m2'), new ReceivedStamp('async')]), $this->stack());

        self::assertTrue(PayloadFingerprint::ofParts('messenger', stdClass::class, '{"stdClass":{"total":1.0,"lines":[{"DateTimeImmutable":{"date":"2026-11-17 12:00:00.000000","timezone_type":3,"timezone":"UTC"}},"a/b"]}}')->equals($this->store->records['messenger|m2']->fingerprint));
    }

    public function testAPrivatePropertyIsPartOfTheContent(): void
    {
        $message = new readonly class('secret-1') {
            public function __construct(
                private string $hidden,
            ) {
            }

            public function hidden(): string
            {
                return $this->hidden;
            }
        };
        $other = new $message('secret-2');

        $this->middleware()->handle(new Envelope($message, [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->middleware()->handle(new Envelope($other, [new IdempotencyStamp('k'), new ReceivedStamp('async')]), $this->stack());
    }

    public function testAContentTooDeepOrNotTextCannotBeRead(): void
    {
        // The message is level 0, its properties 1, the value of `v` 2: thirty arrays put the last one at 32.
        $nested = [];

        for ($level = 0; $level < 30; ++$level) {
            $nested = [$nested];
        }

        $this->middleware()->handle(new Envelope((object) ['v' => $nested], [new IdempotencyStamp('fits'), new ReceivedStamp('async')]), $this->stack());
        self::assertSame(1, $this->handled, '32 levels are read.');

        foreach ([(object) ['v' => [$nested]], (object) ['bytes' => "\xff"], (object) ['file' => fopen('php://memory', 'r')]] as $i => $message) {
            try {
                $this->middleware()->handle(new Envelope($message, [new IdempotencyStamp('k'.$i), new ReceivedStamp('async')]), $this->stack());
                self::fail('An unreadable content was handled.');
            } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
                self::assertNotNull($unrecoverableMessageHandlingException->getPrevious());
            }
        }

        self::assertSame(1, $this->handled);
        self::assertSame(32, IdempotencyMiddleware::MAX_DEPTH);
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

    public function testAStampKeepsTheHexadecimalFingerprintOnly(): void
    {
        self::assertNull(new IdempotencyStamp('k')->fingerprint);
        self::assertSame(PayloadFingerprint::ofParts('a')->hex, new IdempotencyStamp('k', fingerprint: PayloadFingerprint::ofParts('a'))->fingerprint);
    }

    public function testAStampRefusesAMalformedKey(): void
    {
        $this->expectException(InvalidIdempotencyValue::class);

        new IdempotencyStamp(' k');
    }

    private function order(string $order, int $amount): stdClass
    {
        $message = new stdClass();
        $message->order = $order;
        $message->amount = $amount;

        return $message;
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
