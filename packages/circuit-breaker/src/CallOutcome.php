<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use Phil\CircuitBreaker\Exception\InvalidCircuit;

/**
 * How one call to a third party went: whether it failed, and how long it lasted when the caller measured it.
 *
 * The duration is what lets a policy count slow calls: a third party that answers, but too slowly, ties up the
 * workers that wait for it as surely as one that fails. A call whose duration is not known is never slow.
 */
final readonly class CallOutcome
{
    private function __construct(
        public bool $failed,
        public ?int $milliseconds,
    ) {
    }

    /**
     * @param ?int $milliseconds how long the call lasted, at least 0; `null` when it was not measured
     *
     * @throws InvalidCircuit
     */
    public static function succeeded(?int $milliseconds = null): self
    {
        return new self(false, self::duration($milliseconds));
    }

    /**
     * @param ?int $milliseconds how long the call lasted, at least 0; `null` when it was not measured
     *
     * @throws InvalidCircuit
     */
    public static function failed(?int $milliseconds = null): self
    {
        return new self(true, self::duration($milliseconds));
    }

    private static function duration(?int $milliseconds): ?int
    {
        if (null !== $milliseconds && $milliseconds < 0) {
            throw InvalidCircuit::because(\sprintf('The duration of a call is at least 0 milliseconds; got %d.', $milliseconds));
        }

        return $milliseconds;
    }
}
