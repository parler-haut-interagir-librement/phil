<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use Phil\CircuitBreaker\Exception\InvalidCircuit;

/**
 * What one circuit breaker guards: an HTTP client, or a destination key such as one webhook endpoint.
 *
 * One circuit per key, so that one failing third party or one slow destination only ever trips its own.
 * Lowercase ASCII segments separated by ".", "_" or "-", at most {@see self::MAX_LENGTH} bytes: short enough for an
 * adapter to derive a name of its own from it, such as a feature flag of 100 bytes whose 13-byte prefix
 * `circuit-open.` names the circuit it forces open.
 */
final readonly class CircuitKey
{
    public const int MAX_LENGTH = 87;

    private const string PATTERN = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidCircuit
     */
    public static function of(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidCircuit::because(\sprintf('A circuit key is at most %d characters; this one has %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidCircuit::because(\sprintf('"%s" is not a circuit key: use lowercase ASCII segments separated by ".", "_" or "-".', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
