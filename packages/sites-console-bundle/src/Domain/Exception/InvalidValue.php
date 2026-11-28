<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the console or the agent refuses: an identifier, an address, a key, a setting, a version.
 */
final class InvalidValue extends InvalidArgumentException implements SitesConsoleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
