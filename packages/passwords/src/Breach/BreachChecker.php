<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach;

use Phil\Passwords\Breach\Contract\BreachedPasswordSource;
use Phil\Passwords\Exception\BreachCheckUnavailable;
use SensitiveParameter;

/**
 * Checks a password against the breaches a source knows, and never throws: a source that cannot answer gives the
 * status `Unavailable`, with its failure, for the application to accept or refuse the password — fail open or fail
 * closed — and to count the failure in its circuit breaker.
 */
final readonly class BreachChecker
{
    public function __construct(
        private BreachedPasswordSource $source,
    ) {
    }

    /**
     * @param positive-int $threshold the occurrences from which a password is compromised
     */
    public function check(#[SensitiveParameter] string $password, int $threshold = 1): BreachCheck
    {
        try {
            $occurrences = $this->source->occurrences($password);
        } catch (BreachCheckUnavailable $breachCheckUnavailable) {
            return new BreachCheck(BreachStatus::Unavailable, failure: $breachCheckUnavailable);
        }

        return new BreachCheck($occurrences >= $threshold ? BreachStatus::Compromised : BreachStatus::NotFound, $occurrences);
    }
}
