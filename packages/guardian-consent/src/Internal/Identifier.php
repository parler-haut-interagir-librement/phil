<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Internal;

use Phil\GuardianConsent\Exception\InvalidIdentifier;

/**
 * The rules every stable identifier of the package follows.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 255;

    private function __construct()
    {
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidIdentifier
     */
    public static function check(string $value, string $what): string
    {
        if ('' === $value) {
            throw InvalidIdentifier::because(\sprintf('The identifier of a %s cannot be empty.', $what));
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidIdentifier::because(\sprintf('The identifier of a %s is at most %d bytes.', $what, self::MAX_LENGTH));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidIdentifier::because(\sprintf('The identifier of a %s holds no whitespace and no control character.', $what));
        }

        if (str_contains($value, '@')) {
            throw InvalidIdentifier::because(\sprintf('The identifier of a %s is a stable identifier, never an e-mail address: it holds no "@".', $what));
        }

        return $value;
    }
}
