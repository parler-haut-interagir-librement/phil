<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application\Exception;

use InvalidArgumentException;
use Phil\PrintPreflight\Exception\PrintPreflightException;

/**
 * A measured document, a length or a size that cannot be read: a missing or mistyped field, an unknown unit, a font
 * named by family without a resolver.
 */
final class InvalidMeasurement extends InvalidArgumentException implements PrintPreflightException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
