<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Exception;

use InvalidArgumentException;

/**
 * A page given to the check cannot be read: no rendering, or a status code that is not HTTP.
 */
final class InvalidPage extends InvalidArgumentException implements SeoCheckException
{
    public static function noRendering(): self
    {
        return new self('A page is checked from one rendering at least.');
    }

    public static function status(int $status): self
    {
        return new self(\sprintf('%d is not an HTTP status code: a status is between 100 and 599.', $status));
    }

    public static function headerName(string $name): self
    {
        return new self(\sprintf('"%s" is not an HTTP header name.', $name));
    }
}
