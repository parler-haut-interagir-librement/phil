<?php

declare(strict_types=1);

namespace Phil\StructuredData\Exception;

use InvalidArgumentException;

/**
 * A node, a value, an identifier, a language, a nonce or a JSON-LD document the library cannot hold or read as given.
 */
final class InvalidStructuredData extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
