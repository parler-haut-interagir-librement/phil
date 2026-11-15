<?php

declare(strict_types=1);

namespace Phil\Audio;

/**
 * What an external program left: its exit code, its output and its errors.
 */
final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output = '',
        public string $errorOutput = '',
    ) {
    }

    public function isSuccessful(): bool
    {
        return 0 === $this->exitCode;
    }
}
