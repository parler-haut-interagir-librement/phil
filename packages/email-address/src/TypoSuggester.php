<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\Internal\Distance;

/**
 * Suggests the address a person probably meant when its domain is a slip away from a well-known one —
 * `ada@gmial.com`, `ada@hotmail.con`, `ada@gmailcom` — for a form to ask « Did you mean ada@gmail.com? ». It never
 * corrects on its own: the person decides.
 *
 * A domain is compared with each known domain by its optimal string alignment distance: none for a known domain of
 * fewer than eight characters, one slip up to nine, two for a longer one; the closest wins, the first in the list on a tie. Failing that, a
 * top-level domain that is a common slip of `com`, `net` or `org` and is not itself delegated is replaced.
 */
final readonly class TypoSuggester
{
    /**
     * The domains of the largest mailbox providers, most used first.
     */
    public const array DOMAINS = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'aol.com', 'live.com', 'msn.com',
        'me.com', 'mac.com', 'proton.me', 'protonmail.com', 'gmx.com', 'gmx.de', 'gmx.net', 'web.de', 'mail.com',
        'ymail.com', 'yahoo.ca', 'yahoo.fr', 'yahoo.co.uk', 'hotmail.ca', 'hotmail.fr', 'hotmail.co.uk', 'live.ca',
        'live.fr', 'outlook.fr', 'videotron.ca', 'sympatico.ca', 'bell.net', 'rogers.com', 'shaw.ca', 'telus.net',
        'cogeco.ca', 'orange.fr', 'free.fr', 'laposte.net', 'sfr.fr', 'wanadoo.fr', 'comcast.net', 'verizon.net',
        'att.net', 'btinternet.com', 'zoho.com', 'yandex.com', 'fastmail.com', 'hey.com',
    ];

    /**
     * Top-level domains that are slips of a common one and are not delegated themselves.
     */
    public const array TOP_LEVEL_SLIPS = [
        'con' => 'com', 'cmo' => 'com', 'ocm' => 'com', 'comm' => 'com', 'coom' => 'com', 'vom' => 'com',
        'xom' => 'com', 'cpm' => 'com', 'copm' => 'com', 'nte' => 'net', 'ent' => 'net', 'nett' => 'net',
        'ogr' => 'org', 'rog' => 'org', 'orgg' => 'org',
    ];

    private const int SHORT_DOMAIN = 8;

    private const int LONG_DOMAIN = 10;

    /** @var array<string, true> */
    private array $known;

    /**
     * @param list<string>          $domains the domains to suggest, in their ASCII form and lowercase, most used first
     * @param array<string, string> $slips   top-level slips and what they stand for
     */
    public function __construct(
        private array $domains = self::DOMAINS,
        private array $slips = self::TOP_LEVEL_SLIPS,
        private SyntaxPolicy $policy = new SyntaxPolicy(),
    ) {
        $this->known = array_fill_keys($domains, true);
    }

    /**
     * The address meant, or `null` when the domain is a known one or close to none.
     */
    public function suggest(EmailAddress|string $address): ?EmailAddress
    {
        $text = $address instanceof EmailAddress ? $address->toAscii() : trim($address);
        $at = strrpos($text, '@');

        if (false === $at) {
            return null;
        }

        $domain = strtolower(substr($text, $at + 1));

        if (isset($this->known[$domain]) || 1 !== preg_match('/^[a-z0-9.-]{1,253}$/', $domain)) {
            return null;
        }

        $meant = $this->closest($domain) ?? $this->slip($domain);

        try {
            return null === $meant ? null : EmailAddress::parse(substr($text, 0, $at).'@'.$meant, $this->policy);
        } catch (InvalidEmailAddress) {
            return null;
        }
    }

    private function closest(string $domain): ?string
    {
        $closest = null;
        $distance = \PHP_INT_MAX;

        foreach ($this->domains as $known) {
            $length = \strlen($known);

            // A short domain is one slip away from too many real ones: `aol.com` from `aon.com`.
            if ($length < self::SHORT_DOMAIN) {
                continue;
            }

            $limit = $length >= self::LONG_DOMAIN ? 2 : 1;
            $candidate = Distance::between($domain, $known, $limit);

            if ($candidate <= $limit && $candidate < $distance) {
                $closest = $known;
                $distance = $candidate;
            }
        }

        return $closest;
    }

    private function slip(string $domain): ?string
    {
        $tld = strrchr($domain, '.');
        $meant = false === $tld ? null : $this->slips[substr($tld, 1)] ?? null;

        return null === $meant ? null : substr($domain, 0, -\strlen($tld)).'.'.$meant;
    }
}
