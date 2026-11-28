<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use InvalidArgumentException;

/**
 * A transmitter of security events and what the receiver accepts of it: the provider whose linked identities its events
 * are about, the issuer of its tokens, the audiences they must name — the client IDs registered there —, the issuers of
 * the subjects it may name, and how old a token may be.
 *
 * `google()` describes Google's Cross-Account Protection; a package of another provider of the Shared Signals
 * Framework builds its own.
 */
final readonly class TransmitterSettings
{
    public const string GOOGLE_ISSUER = 'https://accounts.google.com/';

    public const string GOOGLE_CONFIGURATION_URL = 'https://accounts.google.com/.well-known/risc-configuration';

    /**
     * @param list<string> $audiences      the audiences accepted, one at least
     * @param list<string> $subjectIssuers the issuers of the subjects an event may name, compared without a trailing slash
     */
    public function __construct(
        public string $provider,
        public string $issuer,
        public array $audiences,
        public array $subjectIssuers,
        public int $maxAge = 604_800,
        public int $clockSkew = 300,
    ) {
        if ('' === $issuer || [] === $audiences || [] === $subjectIssuers || \in_array('', $audiences, true)) {
            throw new InvalidArgumentException('A transmitter has an issuer, one audience at least and the issuers of its subjects.');
        }

        if ($maxAge < 60 || $maxAge > 2_592_000) {
            throw new InvalidArgumentException('A security event token may be 60 seconds to 30 days old.');
        }

        if ($clockSkew < 0 || $clockSkew > 600) {
            throw new InvalidArgumentException('The clock skew is 0 to 600 seconds.');
        }
    }

    /**
     * @param list<string> $audiences the client IDs of the application at Google
     */
    public static function google(array $audiences, int $maxAge = 604_800): self
    {
        return new self(GoogleSettings::NAME, self::GOOGLE_ISSUER, $audiences, [GoogleSettings::ISSUER, 'accounts.google.com'], $maxAge);
    }

    public function isIssuer(string $issuer): bool
    {
        return rtrim($issuer, '/') === rtrim($this->issuer, '/');
    }

    public function acceptsSubjectIssuer(?string $issuer): bool
    {
        return null !== $issuer && \in_array(rtrim($issuer, '/'), array_map(static fn (string $one): string => rtrim($one, '/'), $this->subjectIssuers), true);
    }

    /**
     * @param list<string> $audiences
     */
    public function acceptsAudience(array $audiences): bool
    {
        return [] !== array_intersect($audiences, $this->audiences);
    }
}
