<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use RuntimeException;

/**
 * Going back to a version that would put personal information erased back on its record.
 */
final class VersionErased extends RuntimeException
{
    public static function redacted(string $class, string $id, int $number): self
    {
        return new self(\sprintf('Version %d of %s "%s" was redacted by an erasure: going back to it is refused.', $number, $class, $id));
    }

    public static function erasedRecord(string $class, string $id, int $number): self
    {
        return new self(\sprintf('%s "%s" was erased after version %d: going back to it is refused.', $class, $id, $number));
    }
}
