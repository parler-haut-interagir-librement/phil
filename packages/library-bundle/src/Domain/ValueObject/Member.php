<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\ValueObject;

use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;

/**
 * The shape of the identifier that names a member: 1 to 128 letters, digits, dots, underscores, colons and hyphens,
 * starting with a letter or a digit — an account number, a card number, a UUID. An e-mail address never fits, since an
 * `@` is not among them: an address changes and identifies a person, so the library keeps the application's stable
 * identifier instead.
 */
final readonly class Member
{
    public const int MAX_LENGTH = 128;

    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidLibraryData when the value is not a stable identifier
     */
    public static function check(string $member): string
    {
        if (!self::fits($member)) {
            throw InvalidLibraryData::because('A member is named by a stable identifier of 1 to 128 letters, digits, dots, underscores, colons or hyphens, never by an e-mail address.');
        }

        return $member;
    }

    public static function fits(string $member): bool
    {
        return 1 === preg_match(self::PATTERN, $member);
    }
}
