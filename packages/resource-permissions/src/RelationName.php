<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions;

use Phil\ResourcePermissions\Exception\InvalidRelation;

/**
 * The rule of a relation's name: 1 to 64 lowercase letters, digits, `_`, `.` or `-`, starting with a letter — the
 * rule of a resource kind.
 */
final readonly class RelationName
{
    public const int MAX_LENGTH = 64;

    private function __construct()
    {
    }

    public static function assert(string $relation): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $relation)) {
            throw InvalidRelation::because(\sprintf('A relation is 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $relation));
        }

        return $relation;
    }
}
