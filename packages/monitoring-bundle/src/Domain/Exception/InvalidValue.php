<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the bundle refuses because it does not hold together: a name that is not an identifier, a period of no
 * time, a negative delay.
 */
final class InvalidValue extends InvalidArgumentException implements MonitoringException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
