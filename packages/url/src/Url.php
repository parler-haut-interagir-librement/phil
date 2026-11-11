<?php

declare(strict_types=1);

namespace Phil\Url;

use Override;
use Phil\Url\Exception\InvalidUrl;
use Spoofchecker;
use Stringable;

/**
 * An absolute web address, `http` or `https`, normalised: scheme and host in lowercase, an international host in its
 * ASCII form, the default port dropped, the root path written `/`, an empty query or fragment dropped. Two addresses that differ only so are equal.
 *
 * An address with a user name or a password is refused: it is a phishing device more often than a need. So is an
 * address whose host is an IP address, or port 0: a link on a public page names a host.
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

    public static function fromString(string $text): self
    {
        $text = trim($text);

        if (\strlen($text) > self::MAX_LENGTH) {
            throw InvalidUrl::tooLong(\strlen($text));
        }

        if (1 !== preg_match('#^https?://#i', $text) || 1 === preg_match('/\s/', $text)) {
            throw InvalidUrl::notAUrl($text);
        }

        $parts = parse_url($text);

        if (!\is_array($parts)) {
            throw InvalidUrl::notAUrl($text);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw InvalidUrl::credentials();
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? null;

        if (0 === $port) {
            throw InvalidUrl::port($port);
        }

        return new self(
            $scheme,
            self::checkedHost($parts['host'] ?? ''),
            ['http' => 80, 'https' => 443][$scheme] === $port ? null : $port,
            '' === ($parts['path'] ?? '') ? '/' : $parts['path'],
            '' === ($parts['query'] ?? '') ? null : $parts['query'],
            '' === ($parts['fragment'] ?? '') ? null : $parts['fragment'],
        );
    }

    public function toString(): string
    {
        return $this->scheme.'://'.$this->host
            .(null === $this->port ? '' : ':'.$this->port)
            .$this->path
            .(null === $this->query ? '' : '?'.$this->query)
            .(null === $this->fragment ? '' : '#'.$this->fragment);
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
     * mixing scripts or passing for another host's, is shown in ASCII, so that it cannot pass for that host.
     */
    public function display(): string
    {
        $unicode = idn_to_utf8($this->host, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46);
        // A host whose letters mix scripts or imitate another's, `аpple.com` in Cyrillic, stays in ASCII.
        $host = false === $unicode || $this->checker()->isSuspicious($unicode) ? $this->host : $unicode;
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        $path = '/' === $this->path ? '' : rtrim($this->path, '/');

        return $host.(null === $this->port ? '' : ':'.$this->port).$path.(null === $this->query ? '' : '?'.$this->query);
    }

    /**
     * One spoof checker for every address shown, built for the first one.
     */
    private function checker(): Spoofchecker
    {
        static $checker = null;

        if (!$checker instanceof Spoofchecker) {
            $checker = new Spoofchecker();
        }

        return $checker;
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
     */
    private static function checkedHost(string $host): string
    {
        $ascii = idn_to_ascii(rtrim($host, '.'), \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);

        if (false === $ascii || 1 !== preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $ascii) || 1 === preg_match('/\.[0-9]+$/', $ascii)) {
            throw InvalidUrl::host($host);
        }

        return $ascii;
    }
}
