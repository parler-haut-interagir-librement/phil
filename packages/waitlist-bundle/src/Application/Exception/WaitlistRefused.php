<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Application\Exception;

use RuntimeException;

/**
 * A request the waiting list refuses, with its reason and the values its message needs, such as the most places an
 * entry may ask for.
 */
final class WaitlistRefused extends RuntimeException
{
    /**
     * @param array<string, int> $parameters
     */
    public function __construct(
        public readonly RefusalReason $reason,
        public readonly array $parameters = [],
    ) {
        parent::__construct(\sprintf('The waiting list refuses the request: %s.', $reason->value));
    }
}
