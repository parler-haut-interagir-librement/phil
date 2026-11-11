<?php

declare(strict_types=1);

namespace Phil\Ujo\Exception;

use InvalidArgumentException;

/**
 * A text that is not UJO JSON at all: too large, not JSON, or JSON whose top level is not an object.
 */
final class InvalidJson extends InvalidArgumentException implements UjoException
{
    public static function tooLarge(int $bytes, int $limit): self
    {
        return new self(\sprintf('The UJO JSON is %d bytes long, above the limit of %d bytes.', $bytes, $limit));
    }

    public static function syntax(string $reason): self
    {
        return new self(\sprintf('The UJO JSON cannot be parsed: %s.', $reason));
    }

    public static function notAnObject(): self
    {
        return new self('The UJO JSON must be an object with a "blocks" list.');
    }
}
