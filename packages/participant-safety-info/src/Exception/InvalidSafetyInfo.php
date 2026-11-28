<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Exception;

use InvalidArgumentException;

/**
 * A value refused its own construction: an identifier, a field, a contact, a window or a page out of its bounds. The
 * message names the rule, never the value.
 */
final class InvalidSafetyInfo extends InvalidArgumentException implements ParticipantSafetyInfoException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
