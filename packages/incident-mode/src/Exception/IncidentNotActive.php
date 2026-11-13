<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Exception;

use RuntimeException;

/**
 * The activation does not exist, or is already lifted or past its end: it can be neither lifted nor extended.
 */
final class IncidentNotActive extends RuntimeException implements IncidentModeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No incident activation "%s" is in force.', $id));
    }
}
