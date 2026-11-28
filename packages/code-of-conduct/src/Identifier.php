<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;

/**
 * The rule every identifier of the package follows: a scope, a member or a report is named by 1 to 255 bytes, without
 * a control character, never by an email address the package would have to protect.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_BYTES = 255;

    public static function check(string $value, string $what): string
    {
        if ('' === $value || \strlen($value) > self::MAX_BYTES || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidCodeOfConduct::because(\sprintf('%s is named by 1 to %d bytes, without a control character.', $what, self::MAX_BYTES));
        }

        return $value;
    }
}
