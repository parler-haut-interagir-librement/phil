<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

/**
 * A unit of an imported file that changed nothing, and why.
 */
final readonly class Skipped
{
    public function __construct(
        public string $key,
        public SkipReason $reason,
    ) {
    }
}
