<?php

declare(strict_types=1);

namespace Phil\RelatedContent;

/**
 * What a publication or a retirement computed again, and what it left to compute: the application queues the deferred
 * identifiers, one message each, and calls `RelatedContent::compute()` for them.
 */
final readonly class Recomputation
{
    /**
     * @param list<string> $recomputed
     * @param list<string> $deferred
     */
    public function __construct(
        public array $recomputed,
        public array $deferred,
    ) {
    }
}
