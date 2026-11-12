<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use LogicException;

/**
 * A node that cannot take the place it is given: under itself or one of its descendants, under a parent without a
 * path, or with an identifier a path cannot hold.
 */
final class InvalidTree extends LogicException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
