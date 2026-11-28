<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Exception;

use InvalidArgumentException;
use Phil\Rules\Exception\InvalidRule;

/**
 * A segment the library refuses: an identifier or a name out of form, a definition that is not the versioned JSON
 * form, or a condition type that guesses a trait, scores a behaviour or reads the opening or the clicking of a message.
 */
final class InvalidSegment extends InvalidArgumentException implements AudienceSegmentsException
{
    public static function identifier(string $what, string $id): self
    {
        return new self(\sprintf('"%s" is not a %s identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most.', InvalidRule::shown($id), $what));
    }

    public static function name(): self
    {
        return new self('A segment name holds 1 to 200 bytes of UTF-8, without control characters.');
    }

    public static function forbiddenCondition(string $type): self
    {
        return new self(\sprintf('The condition type "%s" guesses a trait, scores a behaviour or reads the opening or the clicking of a message; a segment never uses one.', $type));
    }

    public static function malformed(string $why): self
    {
        return new self(\sprintf('The segment definition is malformed: %s.', $why));
    }

    public static function unsortedMembers(): self
    {
        return new self('The members of a frozen list are distinct identifiers in ascending byte order.');
    }
}
