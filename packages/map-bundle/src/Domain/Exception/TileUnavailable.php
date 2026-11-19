<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\Exception;

use RuntimeException;

/**
 * A tile the source did not give as the proxy accepts it: answered 502 by the proxy, and not kept.
 */
final class TileUnavailable extends RuntimeException implements MapException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
