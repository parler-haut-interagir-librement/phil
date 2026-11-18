<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;

/**
 * The rule every identifier of the bundle follows: 1 to 128 ASCII letters, digits, "_", "." or "-", starting with a
 * letter or a digit. An identifier carries no personal data and never an email address, so that tables, logs and
 * events hold none; it compares byte for byte.
 */
final readonly class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidOrganization when the text is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (str_contains($value, '@')) {
            throw InvalidOrganization::because(\sprintf('A %s is a stable identifier, never an email address.', $what));
        }

        if (1 !== preg_match(self::PATTERN, $value, $matches)) {
            throw InvalidOrganization::because(\sprintf('A %s is 1 to %d letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $what, self::MAX_LENGTH, $value));
        }

        return $matches[0];
    }
}
