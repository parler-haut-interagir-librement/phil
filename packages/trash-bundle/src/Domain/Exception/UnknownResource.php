<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Domain\Exception;

use LogicException;

/**
 * No handler knows this resource: the application registers a `TrashHandler` for each kind it trashes.
 */
final class UnknownResource extends LogicException implements TrashException
{
    public static function object(object $resource): self
    {
        return new self(\sprintf('No trash handler supports %s: register a TrashHandler for it.', $resource::class));
    }

    public static function kind(string $key): self
    {
        return new self(\sprintf('No trash handler restores "%s" resources: register a TrashHandler for them.', $key));
    }
}
