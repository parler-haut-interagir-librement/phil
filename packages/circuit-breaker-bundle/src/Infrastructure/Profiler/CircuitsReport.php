<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\Profiler;

/**
 * The state of each circuit at the end of one request, as the profiler keeps it between the request and the page
 * that shows it.
 */
final readonly class CircuitsReport
{
    /**
     * @param array<string, string> $circuits circuit key => state
     */
    public function __construct(
        public array $circuits,
    ) {
    }
}
