<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;
use SensitiveParameter;

/**
 * The settings of the provider: its issuer, the algorithm its new keys use, the lifetimes of codes and tokens, the
 * scopes it knows, the rhythm of key rotation, the methods clients may authenticate by, the clock skew and the longest
 * lifetime of a client assertion, whether a refresh token lives no longer than its session — and at most how long
 * after the session opened —, the optional claims of the ID token: `acr`, `idp` and `session_start`, the subject
 * type with the salt opaque and pairwise subjects are derived under, whether client assertions may name their
 * audience the way older clients do, and whether a request without PKCE must carry a `nonce`.
 */
final readonly class ProviderSettings
{
    public const array SCOPES = ['openid', 'profile', 'email', 'phone', 'offline_access'];

    /** The scopes about an account, which a token of the `client_credentials` grant never carries. */
    public const array ACCOUNT_SCOPES = self::SCOPES;

    /**
     * The audiences a client assertion may name: the issuer alone (draft-ietf-oauth-rfc7523bis); with
     * `legacyAssertionAudiences`, the token endpoint at the issuer's origin too.
     *
     * @return list<string>
     */
    public function assertionAudiences(string $tokenEndpointPath): array
    {
        return $this->legacyAssertionAudiences && 1 === preg_match('~^[A-Za-z][A-Za-z0-9+.-]*://[^/]+~', $this->issuer, $origin) && str_starts_with($tokenEndpointPath, '/') ? [$this->issuer, $origin[0].$tokenEndpointPath] : [$this->issuer];
    }

    /**
     * @param list<string>           $scopes
     * @param list<ClientAuthMethod> $authMethods              the methods clients may authenticate by
     * @param int                    $clockSkew                the seconds a time of a client assertion may be off
     * @param ?int                   $sessionMaxAge            the seconds after its session opened past which a refresh token is no
     *                                                         longer issued nor accepted; null for no bound but the session's end
     * @param ?string                $acr                      the `acr` of every ID token, null for none
     * @param ?string                $idp                      the `idp` of every ID token, null for none
     * @param bool                   $sessionStart             whether the ID token carries `session_start`, when its session opened
     * @param bool                   $legacyAssertionAudiences whether a client assertion may also name the token
     *                                                         endpoint, and name its audiences in an array; off, its
     *                                                         `aud` is the issuer, as a string
     * @param bool                   $nonceWithoutPkce         whether an authorization request without a PKCE challenge
     *                                                         must carry a `nonce` (RFC 9700, section 2.1.1)
     */
    public function __construct(
        public string $issuer,
        public SigningAlgorithm $algorithm = SigningAlgorithm::RS256,
        public int $codeLifetime = 60,
        public int $accessTokenLifetime = 600,
        public int $idTokenLifetime = 600,
        public int $refreshTokenLifetime = 2_592_000,
        public array $scopes = self::SCOPES,
        public int $keyActivationDelay = 86_400,
        public int $retiredKeyRetention = 604_800,
        public array $authMethods = ClientAuthMethod::CASES,
        public int $clockSkew = 60,
        public int $assertionMaxLifetime = 300,
        public bool $refreshBoundToSession = true,
        public ?int $sessionMaxAge = null,
        public ?string $acr = null,
        public ?string $idp = null,
        public bool $sessionStart = false,
        public SubjectType $subjectType = SubjectType::Account,
        #[SensitiveParameter]
        public ?string $subjectSalt = null,
        public bool $legacyAssertionAudiences = false,
        public bool $nonceWithoutPkce = true,
    ) {
        if ('' === $issuer || str_ends_with($issuer, '/') || str_contains($issuer, '?') || str_contains($issuer, '#')) {
            throw InvalidValue::because('The issuer is an https address without a trailing slash, query or fragment.');
        }

        if (min($codeLifetime, $accessTokenLifetime, $idTokenLifetime, $refreshTokenLifetime) < 1 || $codeLifetime > 600) {
            throw InvalidValue::because('A lifetime is at least one second, and a code lives at most ten minutes.');
        }

        if (!\in_array('openid', $scopes, true)) {
            throw InvalidValue::because('The provider knows the scope "openid".');
        }

        if ([] === $authMethods) {
            throw InvalidValue::because('Clients authenticate by at least one method.');
        }

        if ($clockSkew < 0 || $clockSkew > 300 || $assertionMaxLifetime < 1 || $assertionMaxLifetime > 3600 || null !== $sessionMaxAge && $sessionMaxAge < 1) {
            throw InvalidValue::because('The clock skew is 0 to 300 seconds, a client assertion lives 1 to 3600 seconds, and a session bound is at least one second.');
        }

        if (SubjectType::Account !== $subjectType && \strlen((string) $subjectSalt) < 32) {
            throw InvalidValue::because('An opaque or pairwise subject is derived under a salt of at least 32 bytes.');
        }

        if ('' === $acr || '' === $idp) {
            throw InvalidValue::because('An optional claim of the ID token is null or a text.');
        }
    }
}
