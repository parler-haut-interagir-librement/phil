<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Reads the `Link` header of RFC 8288, as the services page their lists: the address of a relation — `next`,
 * `differences`, `first`, `last` —, resolved against the address of the request when it is relative. A link whose
 * address is not one is passed over.
 */
final class LinkHeader
{
    /**
     * The longest header read, in bytes.
     */
    public const int MAX_LENGTH = 8192;

    /**
     * @param list<string> $headers the values of the `Link` headers of the answer
     */
    public static function find(array $headers, string $relation, Url $base, bool $allowLocalhost = false): ?Url
    {
        foreach ($headers as $header) {
            if (\strlen($header) > self::MAX_LENGTH) {
                continue;
            }

            preg_match_all('/<([^>]*)>((?:\s*;\s*[A-Za-z*-]+\s*=\s*(?:"[^"]*"|[^;,\s]*))*)/', $header, $links, \PREG_SET_ORDER);

            foreach ($links as [, $target, $parameters]) {
                if (1 !== preg_match('/;\s*rel\s*=\s*(?:"([^"]*)"|([^;,\s]*))/i', $parameters, $rel)) {
                    continue;
                }

                $relations = preg_split('/\s+/', strtolower(trim('' !== $rel[1] ? $rel[1] : $rel[2] ?? '')));

                $found = false !== $relations && \in_array(strtolower($relation), $relations, true) ? self::resolve($target, $base, $allowLocalhost) : null;

                if ($found instanceof Url) {
                    return $found;
                }
            }
        }

        return null;
    }

    private static function resolve(string $target, Url $base, bool $allowLocalhost): ?Url
    {
        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            $target = $base->scheme.'://'.$base->host.(null === $base->port ? '' : ':'.$base->port).$target;
        }

        try {
            return Url::fromString($target, $allowLocalhost);
        } catch (UrlException) {
            return null;
        }
    }
}
