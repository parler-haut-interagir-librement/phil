<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use Override;
use Phil\CircuitBreaker\Contract\CircuitOverride;

/**
 * The overrides of an application together: a circuit is forced open as soon as one of them forces it, and never
 * when there is none. The overrides are asked in their order, and the first that forces the circuit ends the round.
 */
final readonly class CircuitOverrides implements CircuitOverride
{
    /**
     * @param iterable<CircuitOverride> $overrides
     */
    public function __construct(
        private iterable $overrides = [],
    ) {
    }

    #[Override]
    public function isForcedOpen(CircuitKey $key): bool
    {
        foreach ($this->overrides as $override) {
            if ($override->isForcedOpen($key)) {
                return true;
            }
        }

        return false;
    }
}
