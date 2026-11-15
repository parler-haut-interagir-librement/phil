<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * The settings of the provider: its issuer, the algorithm its new keys use, the lifetimes of codes and tokens, the
 * scopes it knows, and the rhythm of key rotation.
 */
final readonly class ProviderSettings
{
    public const array SCOPES = ['openid', 'profile', 'email', 'phone', 'offline_access'];

    /**
     * @param list<string> $scopes
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
    }
}
