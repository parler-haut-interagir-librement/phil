<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Phil\CircuitBreaker\Exception\UnknownCircuit;
use Phil\CircuitBreaker\Tests\Unit\Fixture\FrozenClock;
use Phil\CircuitBreaker\Tests\Unit\Fixture\InMemoryCircuitStore;
use Phil\CircuitBreaker\Tests\Unit\Fixture\SwitchableOverride;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitBreakers::class)]
#[CoversClass(UnknownCircuit::class)]
final class CircuitBreakersTest extends TestCase
{
    public function testABreakerIsFoundByItsKey(): void
    {
        $payments = $this->breaker('http.payments');
        $slack = $this->breaker('slack-webhook');
        $breakers = new CircuitBreakers([$payments, $slack]);

        self::assertSame($payments, $breakers->get(CircuitKey::of('http.payments')));
        self::assertSame($slack, $breakers->get(CircuitKey::of('slack-webhook')));
        self::assertTrue($breakers->has(CircuitKey::of('slack-webhook')));
        self::assertEquals([CircuitKey::of('http.payments'), CircuitKey::of('slack-webhook')], $breakers->keys());
    }

    public function testAnUndeclaredKeyHasNoBreaker(): void
    {
        $breakers = new CircuitBreakers([$this->breaker('http.payments')]);

        self::assertFalse($breakers->has(CircuitKey::of('http.geo')));

        $this->expectException(UnknownCircuit::class);
        $this->expectExceptionMessageIsOrContains('No circuit is declared for the key "http.geo".');

        $breakers->get(CircuitKey::of('http.geo'));
    }

    public function testNoBreakersIsAnEmptyRegistry(): void
    {
        self::assertSame([], new CircuitBreakers([])->keys());
    }

    public function testAKeyIsDeclaredOnce(): void
    {
        $this->expectException(InvalidCircuit::class);
        $this->expectExceptionMessageIsOrContains('The circuit "http.payments" is declared twice.');

        new CircuitBreakers([$this->breaker('http.payments'), $this->breaker('http.payments')]);
    }

    private function breaker(string $key): CircuitBreaker
    {
        return new CircuitBreaker(CircuitKey::of($key), CircuitPolicy::of(50, 2, 60, 30), new InMemoryCircuitStore(), new SwitchableOverride(), new FrozenClock());
    }
}
