<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Exception;

use InvalidArgumentException;

/**
 * No preset of that name is in the catalogue.
 */
final class UnknownPreset extends InvalidArgumentException implements IncidentModeException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No incident preset is named "%s".', $name));
    }
}
