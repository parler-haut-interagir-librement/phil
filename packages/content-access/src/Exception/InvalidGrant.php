<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Exception;

use InvalidArgumentException;

/**
 * A grant the library refuses: a feature or source that is not an identifier, or an end that does not follow its start.
 */
final class InvalidGrant extends InvalidArgumentException implements ContentAccessException
{
    public static function identifier(string $field): self
    {
        return new self(\sprintf('The grant\'s %s is not an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most.', $field));
    }

    public static function endsBeforeItStarts(): self
    {
        return new self('The grant ends at or before its start.');
    }
}
