<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use InvalidArgumentException;

/**
 * A bound of `Limits` or of an operand outside the range the library accepts.
 */
final class InvalidLimit extends InvalidArgumentException implements RulesException
{
    public static function outOfRange(string $limit, int $value, int $min, int $max): self
    {
        return new self(\sprintf('%s is %d; it must lie between %d and %d.', $limit, $value, $min, $max));
    }
}
