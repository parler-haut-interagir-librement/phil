<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use Phil\StructuredData\Exception\InvalidStructuredData;

/**
 * The `@id` of a node: an IRI that names it the same way on every page, so that the graphs of two pages — and the
 * nodes two packages give for one page — speak of one organisation, one site, one person.
 *
 * `Id::of('https://example.com/', 'organization')` is `https://example.com/#organization`: the address of what the
 * node describes, and a fragment saying which node of it.
 */
final class Id
{
    public const int MAX_LENGTH = 2048;

    private const string PATTERN = '/^[^\x00-\x20<>"{}|\\\\^`\x7f]{1,2048}$/Du';

    private const string FRAGMENT = '/^[A-Za-z0-9._~-]{1,128}$/D';

    /**
     * The address without its fragment, then `#` and the fragment.
     *
     * @throws InvalidStructuredData when the address or the fragment cannot make an identifier
     */
    public static function of(string $url, string $fragment): string
    {
        if (1 !== preg_match(self::FRAGMENT, $fragment)) {
            throw InvalidStructuredData::because(\sprintf('The fragment of an identifier is 1 to 128 letters, digits, ".", "_", "~" or "-"; "%s" is not.', $fragment));
        }

        return self::check(explode('#', $url)[0]).'#'.$fragment;
    }

    /**
     * The identifier, checked: an IRI, absolute or relative, or a blank node, without spaces, control characters nor
     * the characters an IRI never holds, of 2048 characters at most.
     *
     * @throws InvalidStructuredData
     */
    public static function check(string $id): string
    {
        if (1 !== preg_match(self::PATTERN, $id)) {
            throw InvalidStructuredData::because(\sprintf('An identifier is an IRI of 1 to %d characters, without spaces nor control characters; "%s" is not.', self::MAX_LENGTH, $id));
        }

        return $id;
    }
}
