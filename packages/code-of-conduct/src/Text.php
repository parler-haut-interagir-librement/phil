<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;

/**
 * The rule every free text of the package follows: trimmed UTF-8, lines and tabs kept, no other control character,
 * within its bounds in characters.
 *
 * @internal
 */
final class Text
{
    public static function check(string $value, int $max, string $rule): string
    {
        $value = trim($value);

        if ('' === $value || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw InvalidCodeOfConduct::because($rule);
        }

        return $value;
    }
}
