<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Phil\ApiContractBundle\Infrastructure\Messenger\TenantIdempotencyScopeMiddleware;
use Phil\ApiContractBundle\Infrastructure\Messenger\TenantScopedIdempotencyStamp;
use Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture\PlaceOrder;
use Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture\RecordingMiddleware;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Messenger\IdempotencyMiddleware;
use Phil\Idempotency\Messenger\IdempotencyStamp;
use Phil\Idempotency\PayloadFingerprint;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[CoversClass(TenantIdempotencyScopeMiddleware::class)]
#[CoversClass(TenantScopedIdempotencyStamp::class)]
final class TenantIdempotencyScopeMiddlewareTest extends TestCase
{
    /** @var array<string, int> */
    private array $handled = [];

    public function testTwoTenantsSendingTheSameKeyAreBothHandledAndAReplayOnce(): void
    {
        $bus = $this->bus(new TenantIdempotencyScopeMiddleware());

        $bus->dispatch($this->received('acme'));
        $bus->dispatch($this->received('globex'));
        $bus->dispatch($this->received('acme'));
        $bus->dispatch($this->received('globex'));

        self::assertSame(['acme' => 1, 'globex' => 1], $this->handled);
    }

    public function testWithoutItTheSecondTenantIsTakenForAReplay(): void
    {
        $bus = $this->bus();

        $bus->dispatch($this->received('acme'));
        $bus->dispatch($this->received('globex'));

        self::assertSame(['acme' => 1], $this->handled, "One shared scope: the second tenant's message is never handled.");
    }

    public function testTheScopeIsTheHashOfTheTenantAndTheProducersScope(): void
    {
        $fingerprint = PayloadFingerprint::ofParts('order', 'o-1');
        $handled = $this->through($this->received('acme', new IdempotencyStamp('key-1', 'orders', $fingerprint)));

        $stamps = $handled->all(IdempotencyStamp::class);
        self::assertCount(1, $stamps, "The producer's stamp is replaced, not kept beside the scoped one.");
        $stamp = $handled->last(IdempotencyStamp::class);
        self::assertInstanceOf(IdempotencyStamp::class, $stamp);
        self::assertSame(['key-1', 'tenant:'.PayloadFingerprint::ofParts('tenant', 'acme', 'scope', 'orders')->hex, $fingerprint->hex], [$stamp->key, $stamp->scope, $stamp->fingerprint]);
        self::assertSame('tenant:', TenantIdempotencyScopeMiddleware::SCOPE_PREFIX);
        self::assertSame('acme', $handled->last(TenantScopedIdempotencyStamp::class)?->tenant);
        self::assertInstanceOf(PlaceOrder::class, $handled->getMessage());
        self::assertInstanceOf(TenantStamp::class, $handled->last(TenantStamp::class));
        self::assertInstanceOf(ReceivedStamp::class, $handled->last(ReceivedStamp::class));

        $withoutFingerprint = $this->through($this->received('acme', new IdempotencyStamp('key-1')))->last(IdempotencyStamp::class);
        self::assertNull($withoutFingerprint?->fingerprint);
        self::assertSame('tenant:'.PayloadFingerprint::ofParts('tenant', 'acme', 'scope', IdempotencyStamp::DEFAULT_SCOPE)->hex, $withoutFingerprint?->scope);
    }

    public function testTheTenantAndTheScopeAreKeptApart(): void
    {
        $one = $this->through($this->received('ab', new IdempotencyStamp('key-1', 'c')))->last(IdempotencyStamp::class);
        $other = $this->through($this->received('a', new IdempotencyStamp('key-1', 'bc')))->last(IdempotencyStamp::class);

        self::assertNotSame($one?->scope, $other?->scope);
    }

    public function testAnEnvelopeThatIsNotReceivedOrLacksAStampPassesAsItIs(): void
    {
        $dispatched = new Envelope(new PlaceOrder('o-1'), [new IdempotencyStamp('key-1'), new TenantStamp('acme')]);
        $noTenant = new Envelope(new PlaceOrder('o-1'), [new IdempotencyStamp('key-1'), new ReceivedStamp('async')]);
        $noKey = new Envelope(new PlaceOrder('o-1'), [new TenantStamp('acme'), new ReceivedStamp('async')]);

        foreach ([$dispatched, $noTenant, $noKey] as $envelope) {
            self::assertEquals($envelope, $this->through($envelope));
        }
    }

    public function testARetryKeepsItsScope(): void
    {
        $fails = true;
        $bus = new MessageBus([
            new TenantIdempotencyScopeMiddleware(),
            new IdempotencyMiddleware(new IdempotencyGuard(new InMemoryIdempotencyStore(), new MockClock('2026-11-17 12:00:00 UTC'))),
            new HandleMessageMiddleware(new HandlersLocator([PlaceOrder::class => [function (PlaceOrder $message) use (&$fails): void {
                if ($fails) {
                    $fails = false;

                    throw new RuntimeException('The first attempt fails.');
                }

                $this->handled['retry'] = ($this->handled['retry'] ?? 0) + 1;
            }]])),
        ]);

        try {
            $bus->dispatch($this->received('acme'));
            self::fail('The first attempt did not fail.');
        } catch (HandlerFailedException $handlerFailedException) {
            $retry = $handlerFailedException->getEnvelope();
        }

        $scope = $retry->last(IdempotencyStamp::class)?->scope;
        self::assertSame('tenant:'.PayloadFingerprint::ofParts('tenant', 'acme', 'scope', IdempotencyStamp::DEFAULT_SCOPE)->hex, $scope);
        self::assertEquals($retry, $this->through($retry), 'A scoped envelope is not scoped twice.');

        $bus->dispatch($retry);
        $bus->dispatch($this->received('acme'));
        self::assertSame(['retry' => 1], $this->handled, 'The retry runs in the scope of the first attempt, and a duplicate afterwards is not handled.');
    }

    public function testAnEnvelopeScopedForAnotherTenantIsRefused(): void
    {
        $envelope = $this->received('globex')->with(new TenantScopedIdempotencyStamp('acme'));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessageIs('The idempotency key of this message was scoped for another tenant than the one it is handled in.');

        $this->through($envelope);
    }

    private function bus(?MiddlewareInterface $scope = null): MessageBus
    {
        $handler = new HandleMessageMiddleware(new HandlersLocator([PlaceOrder::class => [function (PlaceOrder $message): void {
            $this->handled[$message->order] = ($this->handled[$message->order] ?? 0) + 1;
        }]]));
        $idempotency = new IdempotencyMiddleware(new IdempotencyGuard(new InMemoryIdempotencyStore(), new MockClock('2026-11-17 12:00:00 UTC')));

        return new MessageBus($scope instanceof MiddlewareInterface ? [$scope, $idempotency, $handler] : [$idempotency, $handler]);
    }

    /**
     * The envelope as the middleware gives it to the next one.
     */
    private function through(Envelope $envelope): Envelope
    {
        $recorder = new RecordingMiddleware();
        new MessageBus([new TenantIdempotencyScopeMiddleware(), $recorder])->dispatch($envelope);

        return $recorder->envelopes[0];
    }

    /**
     * The same message under the same key, received from a transport in a tenant. The message names its tenant only
     * so that the test counts per tenant; its content is otherwise the same, as the fingerprint of the stamp says.
     */
    private function received(string $tenant, ?IdempotencyStamp $stamp = null): Envelope
    {
        return new Envelope(new PlaceOrder($tenant), [$stamp ?? new IdempotencyStamp('key-1', fingerprint: PayloadFingerprint::ofParts('same intention')), new TenantStamp($tenant), new ReceivedStamp('async')]);
    }
}
