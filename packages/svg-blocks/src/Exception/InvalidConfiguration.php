<?php

declare(strict_types=1);

namespace Phil\SvgBlocks\Exception;

use InvalidArgumentException;

/**
 * A part of the library was built with a value it cannot work with: a secret too short to sign with, an address of
 * the diagram service that is not HTTP, a limit out of range.
 */
final class InvalidConfiguration extends InvalidArgumentException implements SvgBlocksException
{
    public static function of(string $name, string $expected): self
    {
        return new self(\sprintf('The %s must be %s.', $name, $expected));
    }
}
