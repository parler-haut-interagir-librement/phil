<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use RuntimeException;

/**
 * Going back to a past version takes a right that is missing.
 */
final class VersionRestoreDenied extends RuntimeException
{
    public static function of(string $class, string $id, int $number): self
    {
        return new self(\sprintf('Going back to version %d of %s "%s" is not allowed.', $number, $class, $id));
    }
}
