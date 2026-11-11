<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Domain\Exception;

use InvalidArgumentException;

final class InvalidCacheTag extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
