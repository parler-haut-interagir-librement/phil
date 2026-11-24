<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use RuntimeException;
use Throwable;

/**
 * A document refused before it is read: too large, too deep, not UTF-8, with a DOCTYPE, an entity or a processing instruction, or malformed.
 */
final class InvalidXml extends RuntimeException implements SamlException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
