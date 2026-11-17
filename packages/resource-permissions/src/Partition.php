<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Phil\ResourcePermissions\Exception\InvalidRelation;

/**
 * The rule of a partition's name, which a store checks on every call: the default partition, empty, or 1 to
 * {@see self::MAX_LENGTH} bytes of UTF-8 without control characters. Names compare byte by byte.
 */
final readonly class Partition
{
    /**
     * The partition of an application that does not partition its tuples, and of the rows written before partitions.
     */
    public const string DEFAULT = '';

    public const int MAX_LENGTH = 128;

    private function __construct()
    {
    }

    /**
     * @throws InvalidRelation when the name breaks the rule
     */
    public static function check(string $partition): string
    {
        if (self::DEFAULT === $partition) {
            return $partition;
        }

        if (\strlen($partition) > self::MAX_LENGTH) {
            throw InvalidRelation::because(\sprintf('A partition is at most %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($partition)));
        }

        if (1 !== preg_match('/^\P{Cc}+$/Du', $partition)) {
            throw InvalidRelation::because('A partition is UTF-8 without control characters.');
        }

        return $partition;
    }
}
