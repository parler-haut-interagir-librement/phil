<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\DomainName;

/**
 * Reads an SPF record (RFC 7208, section 4.6): `v=spf1`, then mechanisms and modifiers separated by spaces.
 *
 * A record longer than 4096 bytes or of more than 64 terms is refused before it is read; so is an unknown mechanism, a
 * mechanism without the domain it needs, an `all` with a value, an address or a prefix length out of range, and a
 * `redirect` or `exp` given twice: receivers treat each as a permanent error.
 */
final readonly class SpfParser
{
    public const int MAX_LENGTH = 4096;

    public const int MAX_TERMS = 64;

    /**
     * Whether a TXT record is an SPF record: it starts with `v=spf1`, alone or followed by a space.
     */
    public static function isSpf(string $text): bool
    {
        return 1 === preg_match('/^v=spf1(?: |$)/iD', $text);
    }

    /**
     * @throws InvalidRecord
     */
    public function parse(string $text): SpfRecord
    {
        if (\strlen($text) > self::MAX_LENGTH) {
            throw InvalidRecord::because(\sprintf('the record is longer than %d bytes.', self::MAX_LENGTH));
        }

        if (!self::isSpf($text)) {
            throw InvalidRecord::because('the record does not start with "v=spf1".');
        }

        $terms = preg_split('/ +/', substr($text, 6), -1, \PREG_SPLIT_NO_EMPTY);

        if (false === $terms || \count($terms) > self::MAX_TERMS) {
            throw InvalidRecord::because(\sprintf('the record has more than %d terms.', self::MAX_TERMS));
        }

        $mechanisms = [];
        $modifiers = ['redirect' => null, 'exp' => null];

        foreach ($terms as $term) {
            if (1 === preg_match('/^([a-z][a-z0-9_.-]*)=(.*)$/iD', $term, $match)) {
                $name = strtolower($match[1]);

                if (\array_key_exists($name, $modifiers)) {
                    if (null !== $modifiers[$name]) {
                        throw InvalidRecord::because(\sprintf('the modifier "%s" is given twice.', $name));
                    }

                    $modifiers[$name] = $this->domain($match[2], $term);
                }

                continue;
            }

            $mechanisms[] = $this->mechanism($term);
        }

        return new SpfRecord($mechanisms, $modifiers['redirect'], $modifiers['exp']);
    }

    private function mechanism(string $term): SpfMechanism
    {
        if (1 !== preg_match('/^([+~?-]?)([a-z][a-z0-9]*)(.*)$/iD', $term, $match)) {
            throw InvalidRecord::because(\sprintf('the term "%s" is neither a mechanism nor a modifier.', $this->shown($term)));
        }

        $qualifier = Qualifier::from('' === $match[1] ? '+' : $match[1]);
        $name = strtolower($match[2]);
        $value = $match[3];

        $target = match ($name) {
            'all' => '' === $value ? null : throw InvalidRecord::because('the mechanism "all" takes no value.'),
            'include', 'exists' => str_starts_with($value, ':')
                ? $this->domain(substr($value, 1), $term)
                : throw InvalidRecord::because(\sprintf('the mechanism "%s" needs a domain, as in "%s:example.org".', $name, $name)),
            'a', 'mx' => $this->hostWithPrefix($value, $term),
            'ptr' => match (true) {
                '' === $value => null,
                str_starts_with($value, ':') => $this->domain(substr($value, 1), $term),
                default => throw $this->unreadable($term),
            },
            'ip4' => $this->address($value, \FILTER_FLAG_IPV4, 32, $term),
            'ip6' => $this->address($value, \FILTER_FLAG_IPV6, 128, $term),
            default => throw InvalidRecord::because(\sprintf('"%s" is not an SPF mechanism.', $this->shown($name))),
        };

        return new SpfMechanism($qualifier, $name, $target, $value);
    }

    /**
     * `a` and `mx` with an optional domain and optional prefix lengths: `a`, `a/24`, `a:host.example//64`.
     */
    private function hostWithPrefix(string $value, string $term): ?string
    {
        if (1 !== preg_match('#^(?::([^/]+))?(?:/(\d{1,2}))?(?://(\d{1,3}))?$#D', $value, $match)) {
            throw $this->unreadable($term);
        }

        if ((int) ($match[2] ?? '') > 32 || (int) ($match[3] ?? '') > 128) {
            throw $this->unreadable($term);
        }

        return '' === ($match[1] ?? '') ? null : $this->domain($match[1], $term);
    }

    private function address(string $value, int $flag, int $maxPrefix, string $term): null
    {
        if (1 !== preg_match('#^:([^/]+)(?:/(\d{1,3}))?$#D', $value, $match)
            || false === filter_var($match[1], \FILTER_VALIDATE_IP, $flag)
            || (int) ($match[2] ?? '') > $maxPrefix) {
            throw $this->unreadable($term);
        }

        return null;
    }

    /**
     * A domain-spec: a DNS name, or a macro string the parser keeps as written, in lowercase.
     */
    private function domain(string $value, string $term): string
    {
        if (str_contains($value, '%')) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,253}$/D', $value)) {
                throw $this->unreadable($term);
            }

            return strtolower($value);
        }

        return DomainName::dnsName($value) ?? throw InvalidRecord::because(\sprintf('"%s" does not name a valid domain.', $this->shown($term)));
    }

    private function unreadable(string $term): InvalidRecord
    {
        return InvalidRecord::because(\sprintf('the term "%s" cannot be read.', $this->shown($term)));
    }

    private function shown(string $text): string
    {
        return (string) preg_replace('/[^\x20-\x7E]/', '?', substr($text, 0, 80));
    }
}
