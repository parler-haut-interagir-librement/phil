<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\Rules\Operand\Operand;

/**
 * The opaque identifiers the library handles: members, segments, events. Never an address nor a name.
 *
 * @internal
 */
final class Identifier
{
    private function __construct()
    {
    }

    public static function valid(string $id): bool
    {
        return 1 === preg_match(Operand::IDENTIFIER_PATTERN, $id);
    }

    public static function check(string $what, string $id): string
    {
        return self::valid($id) ? $id : throw InvalidSegment::identifier($what, $id);
    }
}
