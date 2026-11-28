<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use OutOfBoundsException;

/**
 * A fact the scope of an evaluation does not hold, read anyway. A condition type asks `FactSource::has()` first when a
 * missing fact is a plain failure rather than a mistake of the application.
 */
final class MissingFact extends OutOfBoundsException implements RulesException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('The scope holds no fact "%s".', InvalidRule::shown($name)));
    }
}
