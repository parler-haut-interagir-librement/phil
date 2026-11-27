<?php

declare(strict_types=1);

namespace Phil\Url;

use Override;
use Phil\Url\Exception\InvalidUrl;
use Stringable;

/**
 * An absolute web address, `http` or `https`, read as a browser reads it, by PHP's URI extension and the WHATWG URL
 * Standard, then normalised: scheme and host in lowercase, an international host in its ASCII form, the default port
 * dropped, the root path written `/`, an empty query or fragment dropped. In the path, a backslash is a slash, the
 * segments `.` and `..` are resolved, written plainly or escaped (`%2e`, `.%2E`), and the characters a path cannot hold
 * — a space, `"`, `<`, `>`, a backtick, braces, a letter outside ASCII — are percent-encoded; so are those the query and
 * the fragment cannot hold, each by its own set of the standard. An escape already written stays as written: `%2F` is
 * a character of its segment, never a separator, and `%7e` stays `%7e`. Two addresses that differ only so are equal.
 * The canonical form, escapes included, is 2048 characters at most.
 *
 * An address with a user name or a password is refused: it is a phishing device more often than a need. So is an
 * address whose host is an IP address, or port 0: a link on a public page names a host. So is `localhost`, and any
 * name under `.localhost`, which RFC 6761 keeps for the loopback address — unless the caller allows them, as an
 * application does in development and in its tests: `Url::fromString($text, allowLocalhost: true)`. Even then, an IP
 * address and any other single name stay refused. The address of a service the application runs beside it, whose host
 * is a bare name or an IP address, is a `ServiceUrl`, read only for the hosts the application declares.
 *
 * No result depends on the locale of the process: an address reads the same whatever `LC_CTYPE` is.
 */
final readonly class Url implements Stringable
{
    public const int MAX_LENGTH = 2048;

    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
        public string $path,
        public ?string $query,
        public ?string $fragment,
    ) {
    }

    /**
     * @param bool $allowLocalhost whether `localhost` and the names under `.localhost` are hosts, for development and
     *                             tests; refused by default
     */
    public static function fromString(string $text, bool $allowLocalhost = false): self
    {
        $parsed = AddressReader::parse($text);

        $url = new self(
            $parsed->getScheme(),
            self::checkedHost((string) $parsed->getAsciiHost(), $allowLocalhost),
            $parsed->getPort(),
            $parsed->getPath(),
            AddressReader::nonEmpty($parsed->getQuery()),
            AddressReader::nonEmpty($parsed->getFragment()),
        );
        AddressReader::checkLength($url->toString());

        return $url;
    }

    public function toString(): string
    {
        return AddressReader::write($this->scheme, $this->host, $this->port, $this->path, $this->query, $this->fragment);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    /**
     * Whether the host is one of these domains, or under one: `m.facebook.com` is on `facebook.com`.
     */
    public function isOn(string ...$domains): bool
    {
        foreach ($domains as $domain) {
            $domain = strtolower($domain);

            if ($this->host === $domain || str_ends_with($this->host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The address as people read it: no scheme, no `www.`, no trailing slash, an international host in its own
     * letters — `example.com/shop`, `bücher.example`. A host that ICU's spoof checker finds suspicious, its letters
     * mixing scripts or passing for another host's, is shown in ASCII, so that it cannot pass for that host; without
     * `ext-intl`, which holds that checker, every international host is shown in ASCII.
     */
    public function display(): string
    {
        $host = HostDisplay::shared()->show($this->host);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        $path = '/' === $this->path ? '' : rtrim($this->path, '/');

        return $host.(null === $this->port ? '' : ':'.$this->port).$this->readable($path.(null === $this->query ? '' : '?'.$this->query));
    }

    /**
     * The escapes of letters written in their own letters, as a browser's address bar shows them: `th%C3%A9` reads
     * `thé`. An escape of ASCII, `%20` or `%2F`, stays; so does one that is no UTF-8, or that decodes to an invisible
     * character, a space or a control, which could hide what the address is.
     */
    private function readable(string $text): string
    {
        return (string) preg_replace_callback('/(?:%[89A-Fa-f][0-9A-Fa-f])+/', static function (array $match): string {
            $decoded = rawurldecode($match[0]);

            return mb_check_encoding($decoded, 'UTF-8') && 1 !== preg_match('/[\p{C}\p{Z}]/u', $decoded) ? $decoded : $match[0];
        }, $text);
    }

    /**
     * The path's segments, empty ones dropped: `['@ada', 'posts']` for `/@ada/posts/`.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path), static fn (string $segment): bool => '' !== $segment));
    }

    /**
     * A host name: labels joined by dots, two at least, the last — the top-level domain — not all digits, which
     * leaves out IPv4 addresses (`192.168.0.1`) and their short forms (`127.1`); an IPv6 address has no such labels.
     * One trailing dot, the root of a fully qualified name, is dropped; a second one is an empty label. `localhost`
     * and the names under `.localhost` are hosts only when allowed; `localhost` is then the one single name accepted.
     */
    private static function checkedHost(string $host, bool $allowLocalhost): string
    {
        $name = str_ends_with($host, '.') ? substr($host, 0, -1) : $host;
        $ascii = match (true) {
            // ICU refuses an empty name with a ValueError, not with false: `https://.` must end in InvalidUrl too.
            '' === $name => false,
            // Lowercase letters, digits, dots and hyphens, no label with hyphens in third and fourth place — no `xn--`
            // label among them: the name is its own ASCII form, IDNA would only check what the pattern below checks.
            // Most hosts skip the conversion, which the polyfill makes slow without ext-intl.
            1 === preg_match('/^[a-z0-9.-]+$/', $name) && 1 !== preg_match('/(?:^|\.)[a-z0-9-]{2}--/', $name) => $name,
            default => idn_to_ascii($name, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46),
        };

        if ($allowLocalhost && 'localhost' === $ascii) {
            return $ascii;
        }

        if (false === $ascii || (!$allowLocalhost && str_ends_with($ascii, '.localhost')) || 1 !== preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $ascii) || 1 === preg_match('/\.[0-9]+$/', $ascii)) {
            throw InvalidUrl::host($host);
        }

        return $ascii;
    }
}
