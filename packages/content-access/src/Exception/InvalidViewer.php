<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Exception;

use InvalidArgumentException;

/**
 * A viewer the library refuses: an empty identifier, a role, an email domain or an address that is not one. The
 * message never repeats the refused value, which may be personal.
 */
final class InvalidViewer extends InvalidArgumentException implements ContentAccessException
{
    public static function field(string $field, string $why): self
    {
        return new self(\sprintf("The viewer's %s %s.", $field, $why));
    }
}
