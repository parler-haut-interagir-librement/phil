<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Exception;

use InvalidArgumentException;

/**
 * A catalogue definition that cannot be applied: a malformed key, an unknown theme or category, a missing label, a
 * catalogue over its bounds.
 */
final class InvalidCatalogue extends InvalidArgumentException implements ContentWarningsException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('Invalid content warning catalogue: %s.', $reason));
    }
}
