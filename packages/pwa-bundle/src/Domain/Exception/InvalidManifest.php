<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A member of the manifest or a setting of the service worker that cannot be: its message names the member and what it
 * must be, never the value it was given.
 */
final class InvalidManifest extends InvalidArgumentException implements PwaException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
