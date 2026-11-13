<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Exception;

use RuntimeException;

/**
 * A link that opens nothing: malformed, unknown, revoked, not yet valid or expired. The message does not say which,
 * so that it tells a person who tries tokens nothing.
 */
final class LinkNotUsable extends RuntimeException implements AccessLinksException
{
    public static function create(): self
    {
        return new self('This link is not valid, or no longer is.');
    }
}
