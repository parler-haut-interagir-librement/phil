<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Where the image at an address comes from, so that no page calls another site behind the reader's back: from the page
 * itself, from a host of the list, or from anywhere else, refused.
 *
 * An address is read as a browser reads it: tabs and line breaks dropped, spaces and controls around it trimmed. A
 * relative path, and an inline `data:image/` one, are the page's own; so is an address of the page's origin. An
 * address of another origin, over `http` or `https`, written whole or from its two slashes, is read by `phil/url`: it is
 * external when its host is one of the list or under one, refused otherwise, as is an address `phil/url` refuses —
 * credentials, an IP address, `localhost` unless allowed. Any other scheme is refused.
 */
final readonly class ImageOrigin
{
    /** The page's own: a relative path, an inline image or the page's origin. */
    public const string LOCAL = 'local';

    /** A host of the list, or under one. */
    public const string EXTERNAL = 'external';

    /** Any other address, or no address. */
    public const string REFUSED = 'refused';

    /**
     * @param list<string> $hosts          the hosts whose images a page may show, those under each included
     * @param bool         $allowLocalhost whether `localhost` and the names under `.localhost` are hosts, in development
     */
    public function __construct(
        private array $hosts = [],
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @param string|null $origin the scheme, host and port of the page, `https://www.example.org`, when there is one
     *
     * @return self::LOCAL|self::EXTERNAL|self::REFUSED
     */
    public function of(string $url, ?string $origin = null): string
    {
        $url = trim(str_replace(["\t", "\n", "\r"], '', $url), "\x00..\x20");

        if ('' === $url) {
            return self::REFUSED;
        }

        if (1 === preg_match('/^data:/i', $url)) {
            return 1 === preg_match('#^data:image/#i', $url) ? self::LOCAL : self::REFUSED;
        }

        // Two slashes, or backslashes, which a browser reads as slashes, start an address of another host; an address
        // without a scheme is a path of the page. Any scheme but http and https is then refused by phil/url.
        if (1 === preg_match('#^[/\\\\]{2}#', $url)) {
            $url = 'https://'.substr($url, 2);
        } elseif (1 !== preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return self::LOCAL;
        }

        if (null !== $origin && '' !== $origin && (0 === strcasecmp($url, $origin) || str_starts_with(strtolower($url), strtolower($origin).'/'))) {
            return self::LOCAL;
        }

        try {
            return Url::fromString($url, $this->allowLocalhost)->isOn(...$this->hosts) ? self::EXTERNAL : self::REFUSED;
        } catch (UrlException) {
            return self::REFUSED;
        }
    }

    /**
     * The origin of a `srcset`, that of its worst piece: refused when one is, external when one is, local when all are;
     * refused without a piece. The set is cut at every space and every comma, which is stricter than HTML: a descriptor,
     * `2x`, reads as a path of the page, and the part of an inline image after a comma too, but no address of another
     * host can hide behind a comma.
     *
     * @return self::LOCAL|self::EXTERNAL|self::REFUSED
     */
    public function ofSet(string $set, ?string $origin = null): string
    {
        $pieces = preg_split('/[\s,]+/', $set, -1, \PREG_SPLIT_NO_EMPTY);
        $origins = array_map(fn (string $piece): string => $this->of($piece, $origin), \is_array($pieces) ? $pieces : []);

        return match (true) {
            [] === $origins, \in_array(self::REFUSED, $origins, true) => self::REFUSED,
            \in_array(self::EXTERNAL, $origins, true) => self::EXTERNAL,
            default => self::LOCAL,
        };
    }
}
