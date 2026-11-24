<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Exception;

use InvalidArgumentException;

/**
 * An option out of its bounds: a limit, a duration, an interval, or a host that is not written in lowercase ASCII.
 */
final class InvalidOption extends InvalidArgumentException implements LinkCheckerException
{
    public static function outOfRange(string $option, float|int $value, float|int $min, float|int $max): self
    {
        return new self(\sprintf('%s is %s; it must lie between %s and %s.', $option, $value, $min, $max));
    }

    public static function host(string $host): self
    {
        return new self(\sprintf('"%s" is not a host name in lowercase ASCII.', mb_substr($host, 0, 100)));
    }
}
