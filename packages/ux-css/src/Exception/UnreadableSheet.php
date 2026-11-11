<?php

declare(strict_types=1);

namespace Phil\UxCss\Exception;

use RuntimeException;

/**
 * A stylesheet that cannot be read from its path.
 */
final class UnreadableSheet extends RuntimeException implements UxCssException
{
    public static function at(string $path): self
    {
        return new self(\sprintf('The stylesheet "%s" cannot be read.', $path));
    }

    public static function outside(string $import): self
    {
        return new self(\sprintf('The stylesheet "%s" is outside the directory of the entry.', $import));
    }
}
