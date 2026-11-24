<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application;

use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\SyntaxPolicy;
use Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint;
use SensitiveParameter;

/**
 * Compares addresses and tells the shared ones, without changing what a person wrote.
 *
 * The normalized form is for comparing and for keys: the part before the @ in Unicode NFC and in lowercase, the domain
 * in lowercase ASCII. It keeps the dots and the `+tag` of the part before the @ — they may name another mailbox — and
 * is never shown nor written back in place of the address as typed.
 *
 * A role address — `info@`, `admin@`, `noreply@` — reaches a shared mailbox or none: a form warns about it and accepts
 * it.
 */
final readonly class EmailAddresses
{
    /**
     * The parts before the @ that name a role rather than a person, in lowercase.
     */
    public const array ROLE_LOCAL_PARTS = [
        'abuse', 'accounting', 'accounts', 'admin', 'administrator', 'billing', 'contact', 'customerservice', 'do-not-reply',
        'donotreply', 'enquiries', 'help', 'helpdesk', 'hostmaster', 'hr', 'info', 'information', 'inquiries', 'invoices',
        'jobs', 'mail', 'mailer-daemon', 'marketing', 'media', 'newsletter', 'no-reply', 'noc', 'noreply', 'office',
        'orders', 'postmaster', 'press', 'privacy', 'root', 'sales', 'security', 'service', 'support', 'team', 'webmaster',
    ];

    /** @var array<string, true> */
    private array $roles;

    private SyntaxPolicy $policy;

    /**
     * @param string       $fingerprintKey the secret the fingerprints are keyed with
     * @param list<string> $roleLocalParts the parts before the @ that name a role, in lowercase
     */
    public function __construct(
        #[SensitiveParameter]
        private string $fingerprintKey,
        array $roleLocalParts = self::ROLE_LOCAL_PARTS,
    ) {
        $this->roles = array_fill_keys(array_map(strtolower(...), $roleLocalParts), true);
        $this->policy = new SyntaxPolicy()->withInternationalLocalPart(true)->withQuotedLocalPart(true)->withIpLiteral(true);
    }

    /**
     * The normalized form of an address, or `null` when the text is not an address.
     */
    public function normalize(#[SensitiveParameter] string $address): ?string
    {
        try {
            $parsed = EmailAddress::parse($address, $this->policy);
        } catch (InvalidEmailAddress) {
            return null;
        }

        return mb_strtolower($parsed->localPart, 'UTF-8').'@'.$parsed->asciiDomain;
    }

    /**
     * Whether two texts are the same address once normalized: `Ada@Example.COM` and `ada@example.com` are.
     */
    public function same(#[SensitiveParameter] string $first, #[SensitiveParameter] string $second): bool
    {
        $normalized = $this->normalize($first);

        return null !== $normalized && $normalized === $this->normalize($second);
    }

    /**
     * The fingerprint of an address, or `null` when the text is not an address.
     */
    public function fingerprint(#[SensitiveParameter] string $address): ?AddressFingerprint
    {
        $normalized = $this->normalize($address);

        return null === $normalized ? null : AddressFingerprint::of($normalized, $this->fingerprintKey);
    }

    /**
     * Whether the address names a role rather than a person; a `+tag` is ignored: `support+billing@` is one.
     */
    public function isRole(#[SensitiveParameter] string $address): bool
    {
        $normalized = $this->normalize($address);

        if (null === $normalized) {
            return false;
        }

        $local = substr($normalized, 0, (int) strrpos($normalized, '@'));
        $plus = strpos($local, '+');

        return isset($this->roles[false === $plus ? $local : substr($local, 0, $plus)]);
    }

    /**
     * @return list<string> the parts before the @ that name a role, for the browser
     */
    public function roleLocalParts(): array
    {
        return array_keys($this->roles);
    }
}
