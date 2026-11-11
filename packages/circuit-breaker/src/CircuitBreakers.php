<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Phil\CircuitBreaker\Exception\UnknownCircuit;

/**
 * The declared circuit breakers, one per key: what a client of a third party asks for its own circuit.
 */
final readonly class CircuitBreakers
{
    /** @var array<string, CircuitBreaker> */
    private array $breakers;

    /**
     * @param list<CircuitBreaker> $breakers
     */
    public function __construct(array $breakers)
    {
        $byKey = [];

        foreach ($breakers as $breaker) {
            if (isset($byKey[$breaker->key->value])) {
                throw InvalidCircuit::because(\sprintf('The circuit "%s" is declared twice.', $breaker->key->value));
            }

            $byKey[$breaker->key->value] = $breaker;
        }

        $this->breakers = $byKey;
    }

    /**
     * @throws UnknownCircuit
     */
    public function get(CircuitKey $key): CircuitBreaker
    {
        return $this->breakers[$key->value] ?? throw UnknownCircuit::keyed($key);
    }

    public function has(CircuitKey $key): bool
    {
        return isset($this->breakers[$key->value]);
    }

    /**
     * @return list<CircuitKey>
     */
    public function keys(): array
    {
        return array_values(array_map(static fn (CircuitBreaker $breaker): CircuitKey => $breaker->key, $this->breakers));
    }
}
