<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use RuntimeException;

/**
 * A rule beyond the bounds of its `Limits`: larger, deeper or longer to evaluate than allowed. Thrown when the rule is
 * loaded, for its size, its depth and its conditions, and when it is evaluated, for its evaluations.
 */
final class LimitExceeded extends RuntimeException implements RulesException
{
    public static function bytes(int $bytes, int $max): self
    {
        return new self(\sprintf('The rule holds %d bytes; at most %d are read.', $bytes, $max));
    }

    public static function depth(string $path, int $max): self
    {
        return new self(\sprintf('The condition at "%s" is nested deeper than %d levels.', $path, $max));
    }

    public static function nodes(int $max): self
    {
        return new self(\sprintf('The rule holds more than %d conditions.', $max));
    }

    public static function evaluations(int $max): self
    {
        return new self(\sprintf('The rule needs more than %d evaluations of its conditions.', $max));
    }
}
