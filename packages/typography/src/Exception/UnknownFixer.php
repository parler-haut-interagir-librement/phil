<?php

declare(strict_types=1);

namespace Phil\Typography\Exception;

use InvalidArgumentException;

/**
 * A fixer name that the presets do not know.
 */
final class UnknownFixer extends InvalidArgumentException implements TypographyException
{
    /**
     * @param list<string> $known
     */
    public static function of(string $name, array $known): self
    {
        return new self(\sprintf('There is no fixer named "%s"; the fixers are: %s.', $name, implode(', ', $known)));
    }
}
