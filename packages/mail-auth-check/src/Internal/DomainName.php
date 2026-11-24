<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Internal;

use Phil\MailAuthCheck\Exception\InvalidDomain;

/**
 * Turns the domain names of a sender profile into the lowercase ASCII form DNS queries use.
 *
 * @internal
 */
final class DomainName
{
    private const int MAX_LENGTH = 253;

    private const string LABEL = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D';

    /**
     * A host name of at least two labels: letters, digits and inner hyphens, an internationalised name converted by
     * ext-intl.
     *
     * @throws InvalidDomain
     */
    public static function toAscii(string $name): string
    {
        $domain = self::ascii($name);
        $labels = explode('.', $domain);

        if (\count($labels) < 2) {
            throw InvalidDomain::because($name, 'it needs at least two labels');
        }

        self::checkLabels($name, $labels);

        return $domain;
    }

    /**
     * A DKIM selector: one or more labels, without the `_domainkey` part.
     *
     * @throws InvalidDomain
     */
    public static function selector(string $name): string
    {
        $selector = self::ascii($name);
        self::checkLabels($name, explode('.', $selector));

        return $selector;
    }

    /**
     * A name an SPF record points to, which may hold underscores (`_spf.provider.example`): its lowercase form without
     * a final dot, or null when it is not a DNS name.
     */
    public static function dnsName(string $name): ?string
    {
        $domain = strtolower($name);

        if (str_ends_with($domain, '.')) {
            $domain = substr($domain, 0, -1);
        }

        if ('' === $domain || \strlen($domain) > self::MAX_LENGTH) {
            return null;
        }

        foreach (explode('.', $domain) as $label) {
            if (1 !== preg_match('/^[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?$/D', $label)) {
                return null;
            }
        }

        return $domain;
    }

    /**
     * The rightmost labels of a domain name.
     */
    public static function rightmost(string $domain, int $count): string
    {
        return implode('.', \array_slice(explode('.', $domain), -$count));
    }

    public static function labelCount(string $domain): int
    {
        return substr_count($domain, '.') + 1;
    }

    private static function ascii(string $name): string
    {
        $domain = strtolower(trim($name));

        if (str_ends_with($domain, '.')) {
            $domain = substr($domain, 0, -1);
        }

        if ('' === $domain) {
            throw InvalidDomain::because($name, 'it is empty');
        }

        if (1 === preg_match('/[\x80-\xFF]/', $domain)) {
            $domain = self::idnToAscii($name, $domain);
        }

        if (\strlen($domain) > self::MAX_LENGTH) {
            throw InvalidDomain::because($name, 'it is longer than 253 characters');
        }

        return $domain;
    }

    private static function idnToAscii(string $name, string $domain): string
    {
        if (!\function_exists('idn_to_ascii')) {
            throw InvalidDomain::needsIntl($name);
        }

        $ascii = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII | \IDNA_CHECK_BIDI | \IDNA_CHECK_CONTEXTJ, \INTL_IDNA_VARIANT_UTS46);

        if (false === $ascii) {
            throw InvalidDomain::because($name, 'it cannot be converted to ASCII');
        }

        return $ascii;
    }

    /**
     * @param list<string> $labels
     */
    private static function checkLabels(string $name, array $labels): void
    {
        foreach ($labels as $label) {
            if (1 !== preg_match(self::LABEL, $label)) {
                throw InvalidDomain::because($name, \sprintf('the label "%s" is not 1 to 63 letters, digits or inner hyphens', substr($label, 0, 63)));
            }
        }
    }
}
