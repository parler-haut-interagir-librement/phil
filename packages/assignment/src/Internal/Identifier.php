<?php

declare(strict_types=1);

namespace Phil\Assignment\Internal;

use Phil\Assignment\Exception\InvalidProblem;

/**
 * The rule every identifier of a problem follows: 1 to 255 bytes, no control character, and never an e-mail address,
 * since an address changes and names a person where a stable opaque identifier does not.
 *
 * @internal
 */
final class Identifier
{
    /** What an e-mail address looks like: something, an at sign, a domain with a dot. */
    private const string EMAIL = '/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/D';

    /**
     * @throws InvalidProblem
     */
    public static function check(string $id, string $what): string
    {
        if ('' === $id || \strlen($id) > 255 || 1 === preg_match('/[\x00-\x1F\x7F]/', $id)) {
            throw InvalidProblem::because(\sprintf('%s identifiers are 1 to 255 bytes without control characters.', ucfirst($what)));
        }

        if (1 === preg_match(self::EMAIL, $id)) {
            throw InvalidProblem::because(\sprintf('%s identifiers are stable opaque identifiers, never e-mail addresses.', ucfirst($what)));
        }

        return $id;
    }
}
