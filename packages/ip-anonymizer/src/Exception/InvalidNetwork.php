<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Exception;

use InvalidArgumentException;

/**
 * A network in CIDR notation, a prefix length or a table of labelled networks cannot be used.
 */
final class InvalidNetwork extends InvalidArgumentException implements IpAnonymizerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
