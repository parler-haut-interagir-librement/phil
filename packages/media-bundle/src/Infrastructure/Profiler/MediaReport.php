<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Profiler;

/**
 * The trace of one request, as the profiler keeps it.
 */
final readonly class MediaReport
{
    /**
     * @param list<array{operation: string, key: string, bytes: int, found: bool}>       $storage
     * @param list<array{format: string, type: string, bytes: int, milliseconds: float}> $renders
     */
    public function __construct(
        public array $storage,
        public array $renders,
    ) {
    }
}
