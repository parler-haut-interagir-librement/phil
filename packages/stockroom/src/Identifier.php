<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * The shape of every identifier and reference the library keeps: 1 to 128 letters, digits, dots, underscores, colons
 * and hyphens, starting with a letter or a digit. An e-mail address never fits, since an `@` is not among them: a
 * person is named by a stable identifier the application gives, never by an address that changes.
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidStock when the value does not have the shape of an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidStock::because(\sprintf('The %s is 1 to %d letters, digits, dots, underscores, colons or hyphens, never an e-mail address: "%s" is not.', $what, self::MAX_LENGTH, mb_strimwidth($value, 0, 40, '…')));
        }

        return $value;
    }

    /**
     * @throws InvalidStock when the value is given and does not have the shape of an identifier
     */
    public static function optional(?string $value, string $what): ?string
    {
        return null === $value ? null : self::check($value, $what);
    }
}
