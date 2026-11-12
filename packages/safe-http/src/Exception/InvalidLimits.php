<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use InvalidArgumentException;

/**
 * Limits outside their bounds, or a header value that could split a request.
 */
final class InvalidLimits extends InvalidArgumentException implements SafeHttpException
{
    public static function outOfRange(string $limit, float|int $value, float|int $min, float|int $max): self
    {
        return new self(\sprintf('%s is %s; it must lie between %s and %s.', $limit, $value, $min, $max));
    }

    public static function header(string $name): self
    {
        return new self(\sprintf('The %s is empty, or holds a line break or a control character.', $name));
    }
}
