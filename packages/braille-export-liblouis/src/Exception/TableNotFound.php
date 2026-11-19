<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Exception;

/**
 * A table liblouis was asked for is not in its table directory.
 */
final class TableNotFound extends LiblouisException
{
    public static function named(string $tables, int $exitCode, string $errorOutput): self
    {
        return new self(\sprintf('liblouis cannot find the table "%s".', $tables), $exitCode, $errorOutput);
    }
}
