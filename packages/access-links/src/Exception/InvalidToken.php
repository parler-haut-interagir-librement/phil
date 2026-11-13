<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Exception;

use InvalidArgumentException;

/**
 * Text that is not a link token: not 43 characters of URL-safe Base64 encoding 32 bytes. The message never repeats the
 * text.
 */
final class InvalidToken extends InvalidArgumentException implements AccessLinksException
{
    public static function malformed(): self
    {
        return new self('A link token is 43 characters of URL-safe Base64.');
    }
}
