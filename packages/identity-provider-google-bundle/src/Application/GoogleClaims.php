<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;

/**
 * The claims of a verified Google ID token mapped to the identity the bundle links: the subject, the address and
 * whether it can be trusted, and the name only when it was asked for.
 *
 * An address is handed over as verified only when Google is its authority, as Google's guidance puts it:
 * `email_verified` is true and the address is a `gmail.com` one, or the account belongs to a Google Workspace domain
 * (`hd`) and the address is on that domain. Any other address — a consumer account on another domain — is handed over
 * unverified, so the bundle never links it to an existing account. With accepted domains configured, an account
 * without `hd`, or of another domain, is refused.
 */
final readonly class GoogleClaims
{
    /** The longest address, RFC 5321. */
    public const int MAX_EMAIL_LENGTH = 254;

    /** The longest name kept, in characters. */
    public const int MAX_NAME_LENGTH = 200;

    public function __construct(
        private GoogleSettings $settings,
    ) {
    }

    /**
     * @param array<array-key, mixed> $claims the claims of the ID token, its signature, issuer, audience, times and
     *                                        nonce checked
     *
     * @throws Refused when the subject is missing, or the account is not of an accepted domain
     */
    public function identity(array $claims): ExternalIdentity
    {
        $subject = $claims['sub'] ?? null;

        if (!\is_string($subject) || 1 !== preg_match('/^[\x21-\x7e]{1,255}$/D', $subject)) {
            throw Refused::because('external.failed');
        }

        $domain = \is_string($claims['hd'] ?? null) ? strtolower($claims['hd']) : null;

        if ([] !== $this->settings->hostedDomains && !\in_array($domain, $this->settings->hostedDomains, true)) {
            throw Refused::because('external.failed');
        }

        $email = $this->email($claims['email'] ?? null);

        return new ExternalIdentity(
            GoogleSettings::NAME,
            $subject,
            $email,
            null !== $email && $this->isVerified($claims['email_verified'] ?? null) && $this->isAuthoritative($email, $domain),
            $this->settings->requestProfile ? $this->name($claims['name'] ?? null) : null,
        );
    }

    private function email(mixed $email): ?string
    {
        if (!\is_string($email) || \strlen($email) > self::MAX_EMAIL_LENGTH || 1 !== preg_match('/^[^@\s]+@[^@\s]+$/D', $email)) {
            return null;
        }

        return $email;
    }

    /**
     * Google's ID tokens carry a boolean; older answers carried the string "true".
     */
    private function isVerified(mixed $verified): bool
    {
        return true === $verified || 'true' === $verified;
    }

    private function isAuthoritative(string $email, ?string $domain): bool
    {
        $emailDomain = strtolower(explode('@', $email)[1]);

        return 'gmail.com' === $emailDomain || $domain === $emailDomain;
    }

    private function name(mixed $name): ?string
    {
        if (!\is_string($name) || !mb_check_encoding($name, 'UTF-8')) {
            return null;
        }

        $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $name));

        return '' === $name ? null : mb_substr($name, 0, self::MAX_NAME_LENGTH);
    }
}
