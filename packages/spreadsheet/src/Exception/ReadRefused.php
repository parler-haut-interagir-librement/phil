<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Exception;

use RuntimeException;

/**
 * A file the library refuses to read: one it cannot open, one over a limit of the reader, one that is unsafe — a
 * document type declared, an entry outside the archive's root — or one that is not well formed.
 */
final class ReadRefused extends RuntimeException implements SpreadsheetException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function overLimit(string $what, int $limit): self
    {
        return new self(\sprintf('%s goes over the limit of %d.', $what, $limit));
    }

    public static function malformed(string $part, string $reason = 'is not well formed'): self
    {
        return new self(\sprintf('The part "%s" %s.', $part, $reason));
    }
}
