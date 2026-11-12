<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use RuntimeException;

/**
 * A record has no frozen version of that number.
 */
final class UnknownVersion extends RuntimeException
{
    public static function of(string $class, string $id, int $number): self
    {
        return new self(\sprintf('%s "%s" has no frozen version %d.', $class, $id, $number));
    }
}
