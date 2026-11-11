<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Domain\Exception;

use InvalidArgumentException;

final class InvalidReference extends InvalidArgumentException implements ReferencesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
