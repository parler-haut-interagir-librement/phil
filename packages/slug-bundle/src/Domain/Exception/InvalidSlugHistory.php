<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A scope or a subject the slug history cannot hold.
 */
final class InvalidSlugHistory extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
