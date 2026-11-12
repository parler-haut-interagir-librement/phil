<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Exception;

use InvalidArgumentException;

/**
 * A policy or a set of rules that cannot be used: a limit below 1, a colour that cannot be read, two rules of one
 * identifier.
 */
final class InvalidPolicy extends InvalidArgumentException implements AccessibilityCheckException
{
    public static function limit(string $name, int $value): self
    {
        return new self(\sprintf('The %s must be at least 1; got %d.', $name, $value));
    }

    public static function colour(string $name, string $value): self
    {
        return new self(\sprintf('The %s must be an opaque CSS colour; got "%s".', $name, $value));
    }

    public static function duplicateRule(string $id): self
    {
        return new self(\sprintf('Two rules have the identifier "%s"; an identifier names one rule.', $id));
    }
}
