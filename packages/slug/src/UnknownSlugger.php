<?php

declare(strict_types=1);

namespace Phil\Slug;

use OutOfBoundsException;

/**
 * No slugger has the name asked for.
 */
final class UnknownSlugger extends OutOfBoundsException
{
    /**
     * @param list<string> $names
     */
    public static function named(string $name, array $names): self
    {
        return new self(\sprintf('No slugger is named "%s"; the sluggers are: %s.', $name, implode(', ', $names)));
    }
}
