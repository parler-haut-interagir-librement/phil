<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Exception;

use InvalidArgumentException;

/**
 * An anonymizer or a salt provider was given an option outside its bounds.
 */
final class InvalidOption extends InvalidArgumentException implements IpAnonymizerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
