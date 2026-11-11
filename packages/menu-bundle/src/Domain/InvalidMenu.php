<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Domain;

use InvalidArgumentException;

/**
 * An item or a menu that cannot be, or a menu no builder makes.
 */
final class InvalidMenu extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
