<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Exception;

use RuntimeException;

/**
 * A JSON document of the protocol that does not have the shape the protocol gives it.
 */
final class MalformedDocument extends RuntimeException implements SitesConsoleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
