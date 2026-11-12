<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Exception;

use InvalidArgumentException;
use Throwable;

final class InvalidMedia extends InvalidArgumentException implements MediaException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
