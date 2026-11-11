<?php

declare(strict_types=1);

namespace Phil\TextDiff\Exception;

use InvalidArgumentException;

/**
 * A text could not be split into tokens: it is not valid UTF-8.
 */
final class InvalidText extends InvalidArgumentException implements TextDiffException
{
    public static function unreadable(): self
    {
        return new self('The text cannot be compared: it is not valid UTF-8.');
    }
}
