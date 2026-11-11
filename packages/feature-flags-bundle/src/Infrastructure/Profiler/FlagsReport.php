<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Profiler;

/**
 * What one request evaluated, as the profiler keeps it between the request and the page that shows it.
 */
final readonly class FlagsReport
{
    /**
     * @param array<string, array{enabled: bool, reason: string, variant: ?string, error: ?string}> $flags
     */
    public function __construct(
        public array $flags,
    ) {
    }
}
