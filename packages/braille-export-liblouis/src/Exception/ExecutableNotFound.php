<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Exception;

/**
 * The `lou_translate` program of liblouis is not installed where it was looked for.
 */
final class ExecutableNotFound extends LiblouisException
{
    public static function named(string $binary): self
    {
        return new self(\sprintf('The liblouis program "%s" is not installed or not executable.', $binary));
    }
}
