<?php

declare(strict_types=1);

namespace Phil\Highlight\Exception;

use InvalidArgumentException;

/**
 * A language was asked for by a name the highlighter does not know.
 */
final class UnknownLanguage extends InvalidArgumentException implements HighlightException
{
    /**
     * @param list<string> $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(\sprintf('There is no language named "%s"; the languages are: %s.', $name, implode(', ', $known)));
    }
}
