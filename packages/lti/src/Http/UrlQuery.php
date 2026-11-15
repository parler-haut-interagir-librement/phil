<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

use Phil\Url\Url;

/**
 * Adds parameters to the query of an address, keeping those it has, without its fragment.
 *
 * @internal
 */
final class UrlQuery
{
    /**
     * @param array<string, string> $parameters
     */
    public static function with(Url $url, array $parameters, bool $allowLocalhost = false): Url
    {
        $query = http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);
        $base = $url->scheme.'://'.$url->host.(null === $url->port ? '' : ':'.$url->port).$url->path;

        return Url::fromString($base.'?'.(null === $url->query ? '' : $url->query.'&').$query, $allowLocalhost);
    }
}
