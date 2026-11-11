<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Domain\Exception;

use InvalidArgumentException;

final class InvalidTrashItem extends InvalidArgumentException implements TrashException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
