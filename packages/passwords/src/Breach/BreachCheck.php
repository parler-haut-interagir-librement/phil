<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach;

use Phil\Passwords\Exception\BreachCheckUnavailable;

/**
 * The outcome of a breach check and the occurrences the source reported, 0 when it could not answer; the failure of
 * the source, when it could not, so that the application logs it or counts it in a circuit breaker.
 */
final readonly class BreachCheck
{
    public function __construct(
        public BreachStatus $status,
        public int $occurrences = 0,
        public ?BreachCheckUnavailable $failure = null,
    ) {
    }

    public function isCompromised(): bool
    {
        return BreachStatus::Compromised === $this->status;
    }

    public function isUnavailable(): bool
    {
        return BreachStatus::Unavailable === $this->status;
    }
}
