<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

use Phil\Url\Url;

/**
 * Adds a segment to the path of an address, keeping its query: the scores of a line item are at `{lineitem}/scores`,
 * whatever query the line item's address carries.
 *
 * @internal
 */
final class UrlPath
{
    public static function append(Url $url, string $segment, bool $allowLocalhost = false): Url
    {
        return Url::fromString($url->scheme.'://'.$url->host.(null === $url->port ? '' : ':'.$url->port).rtrim($url->path, '/').'/'.$segment.(null === $url->query ? '' : '?'.$url->query), $allowLocalhost);
    }
}
