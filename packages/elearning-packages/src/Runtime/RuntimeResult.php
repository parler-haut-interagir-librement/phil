<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * What a call of the data model answers: the value read, the empty string for a write, and the error code of the
 * version, `0` when none, with a diagnostic in a few words.
 */
final readonly class RuntimeResult
{
    public function __construct(
        public string $value = '',
        public int $error = ScormError::NONE,
        public string $diagnostic = '',
    ) {
    }

    public function failed(): bool
    {
        return ScormError::NONE !== $this->error;
    }
}
