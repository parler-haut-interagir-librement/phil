<?php

declare(strict_types=1);

namespace Phil\Checkin;

use Phil\Checkin\Exception\InvalidCheckin;

/**
 * The shape shared by the identifiers of the library: printable ASCII letters, digits, `.`, `_`, `:` and `-`, from one
 * to {@see self::MAX_LENGTH} bytes, so that an identifier of the application — an integer, a UUID, a slug — fits as
 * it is.
 *
 * @internal
 */
final readonly class Identifier
{
    public const int MAX_LENGTH = 64;

    private const string PATTERN = '/^[A-Za-z0-9._:-]{1,64}$/D';

    /**
     * @throws InvalidCheckin
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidCheckin::because(\sprintf('A %s is 1 to %d letters, digits, ".", "_", ":" or "-".', $what, self::MAX_LENGTH));
        }

        return $value;
    }
}
