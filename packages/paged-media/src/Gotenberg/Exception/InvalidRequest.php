<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use InvalidArgumentException;

/**
 * A request that cannot be sent: no file, a file name used twice, a URL that is not absolute HTTP, or conversion options that exclude each other.
 */
final class InvalidRequest extends InvalidArgumentException implements GotenbergException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
