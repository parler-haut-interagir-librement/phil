<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit;

use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Phil\CircuitBreakerBundle\Infrastructure\Configuration\ConfiguredCircuitBreakers;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\InMemoryCircuitStore;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\SwitchableOverride;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredCircuitBreakers::class)]
final class ConfiguredCircuitBreakersTest extends TestCase
{
    private const array POLICY = ['failure_rate_percent' => 50, 'minimum_calls' => 2, 'window_seconds' => 60, 'open_seconds' => 30];

    public function testOneBreakerPerKeyWithItsOwnPolicy(): void
    {
        $clock = new FrozenClock();
        $breakers = ConfiguredCircuitBreakers::fromConfiguration(
            ['http.payments' => self::POLICY, 'slack-webhook' => ['minimum_calls' => 3] + self::POLICY],
            new InMemoryCircuitStore(),
            new SwitchableOverride(),
            $clock,
        );

        self::assertEquals([CircuitKey::of('http.payments'), CircuitKey::of('slack-webhook')], $breakers->keys());

        foreach ([CircuitKey::of('http.payments'), CircuitKey::of('slack-webhook')] as $key) {
            $breakers->get($key)->recordFailure();
            $breakers->get($key)->recordFailure();
        }

        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('http.payments'))->state());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('slack-webhook'))->state());
    }

    /**
     * The policy's default of one trial success, unless the configuration says otherwise.
     */
    public function testTheHalfOpenSuccessesAreRead(): void
    {
        $clock = new FrozenClock();
        $breaker = ConfiguredCircuitBreakers::fromConfiguration(
            ['http.payments' => ['half_open_successes' => 2] + self::POLICY],
            new InMemoryCircuitStore(),
            new SwitchableOverride(),
            $clock,
        )->get(CircuitKey::of('http.payments'));

        $breaker->recordFailure();
        $breaker->recordFailure();

        $clock->advance(30);
        $breaker->recordSuccess();

        self::assertSame(CircuitState::HalfOpen, $breaker->state());
    }

    public function testWithoutHalfOpenSuccessesOneTrialSuccessCloses(): void
    {
        $clock = new FrozenClock();
        $breaker = ConfiguredCircuitBreakers::fromConfiguration(['http.payments' => self::POLICY], new InMemoryCircuitStore(), new SwitchableOverride(), $clock)
            ->get(CircuitKey::of('http.payments'));

        $breaker->recordFailure();
        $breaker->recordFailure();

        $clock->advance(30);
        $breaker->recordSuccess();

        self::assertSame(CircuitState::Closed, $breaker->state());
    }

    public function testTheSlownessThresholdAndRateAreRead(): void
    {
        $clock = new FrozenClock();
        $configured = ['slow_call_milliseconds' => 300, 'slow_call_rate_percent' => 50, 'failure_rate_percent' => 100] + self::POLICY;
        $breaker = ConfiguredCircuitBreakers::fromConfiguration(['http.payments' => $configured], new InMemoryCircuitStore(), new SwitchableOverride(), $clock)
            ->get(CircuitKey::of('http.payments'));

        $breaker->record(CallOutcome::succeeded(20));
        $breaker->record(CallOutcome::succeeded(301));

        self::assertSame(CircuitState::Open, $breaker->state());
    }

    /**
     * Without a rate, every call of the window is slow before the circuit opens: 99 slow calls out of 100 keep it closed.
     */
    public function testWithoutARateAllCallsAreSlowBeforeItOpens(): void
    {
        $configured = ['slow_call_milliseconds' => 300, 'minimum_calls' => 100, 'failure_rate_percent' => 100] + self::POLICY;
        $breaker = ConfiguredCircuitBreakers::fromConfiguration(['http.payments' => $configured], new InMemoryCircuitStore(), new SwitchableOverride(), new FrozenClock())
            ->get(CircuitKey::of('http.payments'));

        $breaker->record(CallOutcome::succeeded(20));

        for ($slow = 0; $slow < 99; ++$slow) {
            $breaker->record(CallOutcome::succeeded(301));
        }

        self::assertSame(CircuitState::Closed, $breaker->state());
    }

    public function testWithoutAThresholdNoCallIsSlow(): void
    {
        $breaker = ConfiguredCircuitBreakers::fromConfiguration(['http.payments' => ['slow_call_milliseconds' => null] + self::POLICY], new InMemoryCircuitStore(), new SwitchableOverride(), new FrozenClock())
            ->get(CircuitKey::of('http.payments'));

        $breaker->record(CallOutcome::succeeded(\PHP_INT_MAX));
        $breaker->record(CallOutcome::succeeded(\PHP_INT_MAX));

        self::assertSame(CircuitState::Closed, $breaker->state());
    }

    #[DoesNotPerformAssertions]
    public function testValidationAcceptsAWellFormedCircuit(): void
    {
        ConfiguredCircuitBreakers::validate(['http.payments' => self::POLICY]);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function malformedCircuits(): iterable
    {
        yield 'not a map' => [['http.payments' => 50], 'circuits.http.payments: A circuit is declared by a map of its policy.'];
        yield 'missing value' => [['http.payments' => ['open_seconds' => null] + self::POLICY], 'circuits.http.payments: "open_seconds" is a whole number, and it is required.'];
        yield 'not an integer' => [['http.payments' => ['minimum_calls' => '2'] + self::POLICY], 'circuits.http.payments: "minimum_calls" is a whole number, and it is required.'];
        yield 'out of range' => [['http.payments' => ['failure_rate_percent' => 0] + self::POLICY], 'circuits.http.payments: A failure rate threshold is between 1 and 100 %; got 0.'];
        yield 'threshold not an integer' => [['http.payments' => ['slow_call_milliseconds' => '300'] + self::POLICY], 'circuits.http.payments: "slow_call_milliseconds" is a whole number when it is given.'];
        yield 'slow call rate not an integer' => [['http.payments' => ['slow_call_rate_percent' => '50'] + self::POLICY], 'circuits.http.payments: "slow_call_rate_percent" is a whole number, and it is required.'];
        yield 'slow call rate out of range' => [['http.payments' => ['slow_call_milliseconds' => 300, 'slow_call_rate_percent' => 0] + self::POLICY], 'circuits.http.payments: A slow call rate threshold is between 1 and 100 %; got 0.'];
        yield 'malformed key' => [['Payments' => self::POLICY], 'circuits.Payments: "Payments" is not a circuit key'];
    }

    /**
     * @param array<array-key, mixed> $circuits
     */
    #[DataProvider('malformedCircuits')]
    public function testAMalformedCircuitIsRefusedWithItsPath(array $circuits, string $message): void
    {
        $this->expectException(InvalidCircuit::class);
        $this->expectExceptionMessageIsOrContains($message);

        ConfiguredCircuitBreakers::validate($circuits);
    }

    /**
     * @param array<array-key, mixed> $circuits
     */
    #[DataProvider('malformedCircuits')]
    public function testAMalformedCircuitBuildsNoBreaker(array $circuits, string $message): void
    {
        $this->expectException(InvalidCircuit::class);
        $this->expectExceptionMessageIsOrContains($message);

        ConfiguredCircuitBreakers::fromConfiguration($circuits, new InMemoryCircuitStore(), new SwitchableOverride(), new FrozenClock());
    }
}
