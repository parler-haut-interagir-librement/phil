<?php

declare(strict_types=1);

namespace Phil\Address\Exception;

use RuntimeException;

/**
 * Address data to import that is not what the importer reads.
 */
final class InvalidData extends RuntimeException implements AddressException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
