<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\Configuration;

use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\Contract\CircuitOverride;
use Phil\CircuitBreaker\Contract\CircuitStateStore;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Psr\Clock\ClockInterface;

/**
 * The circuit breakers declared in the application configuration, one policy per key:
 *
 * ```yaml
 * circuits:
 *     http.payments:
 *         failure_rate_percent: 50
 *         minimum_calls: 10
 *         window_seconds: 60
 *         open_seconds: 30
 *         half_open_successes: 1
 *         slow_call_milliseconds: 2000
 *         slow_call_rate_percent: 50
 * ```
 */
final class ConfiguredCircuitBreakers
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $circuits circuit key => policy
     *
     * @throws InvalidCircuit
     */
    public static function fromConfiguration(
        array $circuits,
        CircuitStateStore $store,
        CircuitOverride $override,
        ClockInterface $clock,
    ): CircuitBreakers {
        $breakers = [];

        foreach (self::policies($circuits) as [$key, $policy]) {
            $breakers[] = new CircuitBreaker($key, $policy, $store, $override, $clock);
        }

        return new CircuitBreakers($breakers);
    }

    /**
     * Reads every key and policy without building a breaker, so that a malformed circuit fails the container
     * build rather than the first call to its third party.
     *
     * @param array<array-key, mixed> $circuits
     *
     * @throws InvalidCircuit
     */
    public static function validate(array $circuits): void
    {
        self::policies($circuits);
    }

    /**
     * @param array<array-key, mixed> $circuits
     *
     * @return list<array{CircuitKey, CircuitPolicy}>
     */
    private static function policies(array $circuits): array
    {
        $policies = [];

        foreach ($circuits as $key => $policy) {
            try {
                $policies[] = [CircuitKey::of((string) $key), self::policy($policy)];
            } catch (InvalidCircuit $invalidValue) {
                throw InvalidCircuit::because(\sprintf('circuits.%s: %s', $key, $invalidValue->getMessage()));
            }
        }

        return $policies;
    }

    private static function policy(mixed $policy): CircuitPolicy
    {
        if (!\is_array($policy)) {
            throw InvalidCircuit::because('A circuit is declared by a map of its policy.');
        }

        return CircuitPolicy::of(
            self::integer($policy, 'failure_rate_percent'),
            self::integer($policy, 'minimum_calls'),
            self::integer($policy, 'window_seconds'),
            self::integer($policy, 'open_seconds'),
            self::integer($policy, 'half_open_successes', 1),
            self::optionalInteger($policy, 'slow_call_milliseconds'),
            self::integer($policy, 'slow_call_rate_percent', 100),
        );
    }

    /**
     * @param array<array-key, mixed> $policy
     */
    private static function integer(array $policy, string $name, ?int $default = null): int
    {
        $value = $policy[$name] ?? $default;

        if (!\is_int($value)) {
            throw InvalidCircuit::because(\sprintf('"%s" is a whole number, and it is required.', $name));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $policy
     */
    private static function optionalInteger(array $policy, string $name): ?int
    {
        $value = $policy[$name] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw InvalidCircuit::because(\sprintf('"%s" is a whole number when it is given.', $name));
        }

        return $value;
    }
}
