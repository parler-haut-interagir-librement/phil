<?php

declare(strict_types=1);

namespace Phil\TextDiff\Exception;

use InvalidArgumentException;

/**
 * The differ was built with a bound it cannot work with.
 */
final class InvalidConfiguration extends InvalidArgumentException implements TextDiffException
{
    public static function maxEdits(int $maxEdits): self
    {
        return new self(\sprintf('The number of edits searched for cannot be negative; got %d.', $maxEdits));
    }
}
