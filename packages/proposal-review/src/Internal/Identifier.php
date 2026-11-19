<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Internal;

use Phil\ProposalReview\Exception\InvalidInput;

/**
 * The rule every identifier follows: 1 to 255 bytes, no control character, and never an e-mail address, since an
 * address changes and names a person where a stable opaque identifier does not.
 *
 * @internal
 */
final class Identifier
{
    /** What an e-mail address looks like: something, an at sign, a domain with a dot. */
    private const string EMAIL = '/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/D';

    /**
     * @throws InvalidInput
     */
    public static function check(string $id, string $what): string
    {
        if ('' === $id || \strlen($id) > 255 || 1 === preg_match('/[\x00-\x1F\x7F]/', $id)) {
            throw InvalidInput::because(\sprintf('%s identifiers are 1 to 255 bytes without control characters.', ucfirst($what)));
        }

        if (1 === preg_match(self::EMAIL, $id)) {
            throw InvalidInput::because(\sprintf('%s identifiers are stable opaque identifiers, never e-mail addresses.', ucfirst($what)));
        }

        return $id;
    }

    /**
     * Several identifiers, each checked, none twice.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public static function unique(array $ids, string $what): array
    {
        foreach ($ids as $id) {
            self::check($id, $what);
        }

        if (\count(array_unique($ids)) !== \count($ids)) {
            throw InvalidInput::because(\sprintf('%s identifiers are given once each.', ucfirst($what)));
        }

        return $ids;
    }
}
