<?php

declare(strict_types=1);

namespace Phil\ApiContract\Deprecation;

/**
 * Just enough of RFC 8288 to find the target of a given link relation.
 *
 * A `Link` header holds comma-separated `<uri>; param=value` entries, and a response may repeat the header.
 * `rel` holds one or more space-separated relation types, compared case-insensitively.
 */
final class LinkHeader
{
    private const string ENTRY = '/<([^>]*)>((?:\s*;\s*[A-Za-z0-9!#$%&\'*+.^_`|~-]+\s*(?:=\s*(?:"(?:[^"\\\\]|\\\\.)*"|[^;,\s]*))?)*)/';

    /**
     * One parameter, anchored where the previous one ended: a `; rel=` inside another parameter's quoted value
     * is never read as a parameter of its own.
     */
    private const string PARAMETER = '/\G\s*;\s*([A-Za-z0-9!#$%&\'*+.^_`|~-]+)\s*(?:=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^;,\s]*)))?/';

    private function __construct()
    {
    }

    /**
     * @param list<string> $headers
     */
    public static function firstWithRelation(array $headers, string $relation): ?string
    {
        foreach ($headers as $header) {
            if (false === preg_match_all(self::ENTRY, $header, $entries, \PREG_SET_ORDER)) {
                continue;
            }

            foreach ($entries as $entry) {
                if ('' !== $entry[1] && self::hasRelation($entry[2], $relation)) {
                    return $entry[1];
                }
            }
        }

        return null;
    }

    private static function hasRelation(string $parameters, string $relation): bool
    {
        if (false === preg_match_all(self::PARAMETER, $parameters, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL)) {
            return false;
        }

        foreach ($matches as $parameter) {
            if (0 !== strcasecmp($parameter[1], 'rel')) {
                continue;
            }

            // RFC 8288: only the first `rel` of a link counts. Group 3 is the bare token, group 2 the quoted form.
            $value = $parameter[3] ?? stripslashes($parameter[2] ?? '');
            // Surrounding whitespace only yields empty types, which never match a relation.
            $types = preg_split('/\s+/', $value);

            return false !== $types && array_any($types, static fn (string $type): bool => 0 === strcasecmp($type, $relation));
        }

        return false;
    }
}
