<?php

declare(strict_types=1);

namespace Phil\Geo\Exception;

use RuntimeException;

/**
 * A MaxMind database that cannot be used: missing, unreadable, or not a MaxMind DB file. It is a fault of the
 * installation, not of the address looked up.
 */
final class InvalidDatabase extends RuntimeException implements GeoException
{
    public static function unreadable(string $path): self
    {
        return new self(\sprintf('The MaxMind database "%s" cannot be read.', $path));
    }

    public static function corrupt(string $path, string $reason): self
    {
        return new self(\sprintf('The MaxMind database "%s" is not a valid MaxMind DB file: %s', $path, $reason));
    }
}
