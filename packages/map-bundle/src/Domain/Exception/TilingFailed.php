<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\Exception;

use RuntimeException;

/**
 * A large image that could not be cut into tiles: unreadable, of a type the tiler does not read, too large, or a tile
 * that could not be written.
 */
final class TilingFailed extends RuntimeException implements MapException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
