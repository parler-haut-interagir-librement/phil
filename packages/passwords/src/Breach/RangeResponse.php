<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach;

use Phil\Passwords\Exception\BreachCheckUnavailable;

/**
 * The body of an answer of the Pwned Passwords range API, read: one line for each hash that starts with the prefix
 * asked, its 35 remaining hexadecimal characters, a colon and its occurrences, the padding lines counting 0.
 *
 * The body is read line by line without a regular expression, in one pass; a line of another form makes the whole
 * answer malformed, so that a broken mirror is noticed rather than read as "not found".
 */
final class RangeResponse
{
    public const int SUFFIX_LENGTH = 35;

    /** Occurrences longer than this many digits are capped: they would not fit in an integer. */
    private const int MAX_DIGITS = 18;

    /**
     * The occurrences of the suffix in the body, 0 when it is not there.
     *
     * @param string $suffix the 35 last hexadecimal characters of the SHA-1 hash, in any case
     *
     * @throws BreachCheckUnavailable when a line of the body is not a suffix and a count
     */
    public static function occurrences(string $body, string $suffix): int
    {
        $suffix = strtoupper($suffix);
        $found = 0;

        foreach (explode("\n", $body) as $line) {
            $line = rtrim($line, "\r");

            if ('' === $line) {
                continue;
            }

            $count = substr($line, self::SUFFIX_LENGTH + 1);

            if (':' !== ($line[self::SUFFIX_LENGTH] ?? '') || !ctype_xdigit(substr($line, 0, self::SUFFIX_LENGTH)) || !ctype_digit($count)) {
                throw BreachCheckUnavailable::malformed();
            }

            if (strtoupper(substr($line, 0, self::SUFFIX_LENGTH)) === $suffix) {
                $found = \strlen($count) > self::MAX_DIGITS ? \PHP_INT_MAX : (int) $count;
            }
        }

        return $found;
    }
}
