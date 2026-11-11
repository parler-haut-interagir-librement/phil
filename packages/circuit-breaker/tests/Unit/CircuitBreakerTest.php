<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreaker\Tests\Unit\Fixture\FrozenClock;
use Phil\CircuitBreaker\Tests\Unit\Fixture\InMemoryCircuitStore;
use Phil\CircuitBreaker\Tests\Unit\Fixture\SwitchableOverride;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitBreaker::class)]
final class CircuitBreakerTest extends TestCase
{
    private FrozenClock $clock;

    private InMemoryCircuitStore $store;

    private SwitchableOverride $override;

    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->store = new InMemoryCircuitStore();
        $this->override = new SwitchableOverride();
        $this->breaker = new CircuitBreaker(
            CircuitKey::of('http.payments'),
            CircuitPolicy::of(50, 2, 60, 30, 1),
            $this->store,
            $this->override,
            $this->clock,
        );
    }

    public function testANeverSeenCircuitIsClosedAndWritesNothing(): void
    {
        self::assertTrue($this->breaker->isAvailable());
        self::assertSame(CircuitState::Closed, $this->breaker->state());
        self::assertSame(0, $this->store->saves);
    }

    public function testTheFullCycleClosedOpenHalfOpenClosed(): void
    {
        $this->breaker->recordFailure();
        $this->breaker->recordFailure();
        self::assertFalse($this->breaker->isAvailable());
        self::assertSame(CircuitState::Open, $this->breaker->state());

        $this->clock->advance(29);
        self::assertFalse($this->breaker->isAvailable());

        $this->clock->advance(1);
        self::assertTrue($this->breaker->isAvailable());
        self::assertSame(CircuitState::HalfOpen, $this->store->snapshots['http.payments']->state, 'the transition is stored');

        $this->breaker->recordSuccess();
        self::assertSame(CircuitState::Closed, $this->breaker->state());
    }

    public function testAStateIsSharedThroughTheStore(): void
    {
        $this->breaker->recordFailure();
        $this->breaker->recordFailure();

        $other = new CircuitBreaker(CircuitKey::of('http.payments'), CircuitPolicy::of(50, 2, 60, 30), $this->store, $this->override, $this->clock);
        $unrelated = new CircuitBreaker(CircuitKey::of('http.search'), CircuitPolicy::of(50, 2, 60, 30), $this->store, $this->override, $this->clock);

        self::assertFalse($other->isAvailable());
        self::assertTrue($unrelated->isAvailable(), 'one circuit per key');
    }

    public function testAForcedOpenCircuitIsUnavailableAndRecordsNothing(): void
    {
        $this->override->forcedOpen = true;

        self::assertFalse($this->breaker->isAvailable());
        self::assertSame(CircuitState::Open, $this->breaker->state());

        $this->breaker->recordFailure();
        $this->breaker->recordSuccess();
        $this->breaker->record(CallOutcome::failed(10));
        self::assertSame(0, $this->store->saves);

        $this->override->forcedOpen = false;
        self::assertTrue($this->breaker->isAvailable());
    }

    public function testSlowCallsReportedWithTheirDurationOpenTheCircuit(): void
    {
        $breaker = new CircuitBreaker(CircuitKey::of('http.search'), CircuitPolicy::of(50, 2, 60, 30, 1, 500, 100), $this->store, $this->override, $this->clock);

        $breaker->record(CallOutcome::succeeded(900));
        self::assertTrue($breaker->isAvailable());
        self::assertSame(1, $this->store->saves);

        $breaker->record(CallOutcome::succeeded(501));
        self::assertFalse($breaker->isAvailable());
        self::assertSame(CircuitState::Open, $breaker->state());
    }
}
