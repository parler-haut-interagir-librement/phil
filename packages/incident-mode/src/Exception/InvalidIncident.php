<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Exception;

use InvalidArgumentException;

/**
 * What a preset, a restriction, a duration, an actor or an activation cannot be made of.
 */
final class InvalidIncident extends InvalidArgumentException implements IncidentModeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
