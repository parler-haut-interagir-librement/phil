<?php

declare(strict_types=1);

namespace Phil\LinkChecker;

use Phil\Url\Url;

/**
 * The key under which two links to the same address are checked once. phil/url already writes the scheme and the host
 * in lowercase, an international host in ASCII, and drops the default port; the key drops the fragment too, which never
 * reaches the server.
 *
 * The parameters of the query keep their order unless `$sortQuery` is set: `?b=2&a=1` and `?a=1&b=2` are the same page
 * for most sites, not for all, so merging them is a choice the caller makes. Sorted, the parameters are ordered by
 * name, and those of the same name keep their order.
 */
final readonly class UrlNormalizer
{
    public function __construct(
        public bool $sortQuery = false,
    ) {
    }

    public function key(Url $url): string
    {
        $query = $url->query;

        if ($this->sortQuery && null !== $query) {
            $query = $this->sorted($query);
        }

        return $this->write($url, $query);
    }

    /**
     * The address as requested: the same, without its fragment.
     */
    public function withoutFragment(Url $url): Url
    {
        return null === $url->fragment ? $url : Url::fromString($this->write($url, $url->query), allowLocalhost: true);
    }

    private function write(Url $url, ?string $query): string
    {
        return $url->scheme.'://'.$url->host.(null === $url->port ? '' : ':'.$url->port).$url->path.(null === $query ? '' : '?'.$query);
    }

    private function sorted(string $query): string
    {
        $pairs = explode('&', $query);
        // usort() is stable: the parameters of the same name keep their order.
        usort($pairs, static fn (string $a, string $b): int => strcmp(self::name($a), self::name($b)));

        return implode('&', $pairs);
    }

    private static function name(string $pair): string
    {
        $equals = strpos($pair, '=');

        return false === $equals ? $pair : substr($pair, 0, $equals);
    }
}
