<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A snapshot asked for a target the configuration does not declare.
 */
final class UnknownSnapshotTarget extends InvalidArgumentException implements StatusPageException
{
    /**
     * @param list<string> $known
     */
    public static function named(string $target, array $known): self
    {
        return new self(\sprintf('No snapshot target is named "%s"; the targets are: %s.', $target, [] === $known ? 'none' : implode(', ', $known)));
    }
}
