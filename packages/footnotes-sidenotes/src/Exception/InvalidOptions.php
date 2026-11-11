<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Exception;

use InvalidArgumentException;

/**
 * An option that breaks a rule: a heading level out of range, an empty list of symbols, a malformed prefix of
 * identifiers.
 */
final class InvalidOptions extends InvalidArgumentException implements FootnotesException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('Invalid options: %s.', $reason));
    }
}
