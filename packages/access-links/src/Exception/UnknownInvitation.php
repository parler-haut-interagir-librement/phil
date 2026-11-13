<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Exception;

use RuntimeException;

/**
 * An invitation the store does not hold.
 */
final class UnknownInvitation extends RuntimeException implements AccessLinksException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No invitation has the identifier "%s".', $id));
    }
}
