<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use DateTimeImmutable;
use Jose\Component\Core\JWK;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * The client assertions of `private_key_jwt` (OpenID Connect Core, section 9; RFC 7523, section 3): a JSON Web Token
 * the client signed with a private key whose public key it registered, the key its `kid` names, with the one algorithm
 * of that key.
 *
 * - `iss` and `sub` are the client's identifier; `aud` names the issuer, or the token endpoint at the issuer's origin.
 * - `exp` is required, not past by more than the clock skew, and no further ahead than the longest lifetime of an
 *   assertion; `iat` and `nbf`, when present, are not ahead by more than the skew.
 * - `jti` is required, at most MAX_JTI_LENGTH bytes, and used once: the assertion is recorded until it expires, the
 *   skew added, and refused the second time — two requests presenting it at once included.
 *
 * Every refusal is `invalid_client`, without saying which check failed.
 */
final readonly class ClientAssertions
{
    public const string TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    public const int MAX_JTI_LENGTH = 256;

    public function __construct(
        private GrantRepository $grants,
        private ProviderSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $audiences the audiences the assertion may name
     *
     * @throws OAuthError `invalid_client`
     */
    public function verify(Client $client, #[SensitiveParameter] string $assertion, array $audiences): void
    {
        $claims = $this->verifiedClaims($client, $assertion) ?? throw OAuthError::invalidClient();

        $now = $this->clock->now()->getTimestamp();
        $skew = $this->settings->clockSkew;
        $audience = $claims['aud'] ?? null;
        $named = \is_string($audience) ? [$audience] : array_filter(\is_array($audience) ? $audience : [], is_string(...));
        $expires = $claims['exp'] ?? null;
        $jti = $claims['jti'] ?? null;

        if (($claims['iss'] ?? null) !== $client->id() || ($claims['sub'] ?? null) !== $client->id() || [] === array_intersect($named, $audiences)) {
            throw OAuthError::invalidClient();
        }

        if (!\is_int($expires) || $expires + $skew <= $now || $expires - $now > $this->settings->assertionMaxLifetime || !$this->notAhead($claims['iat'] ?? null, $now + $skew) || !$this->notAhead($claims['nbf'] ?? null, $now + $skew)) {
            throw OAuthError::invalidClient();
        }

        if (!\is_string($jti) || '' === $jti || \strlen($jti) > self::MAX_JTI_LENGTH || !$this->grants->useAssertion(UsedAssertion::record($client->id(), $jti, new DateTimeImmutable()->setTimestamp($expires + $skew)))) {
            throw OAuthError::invalidClient();
        }
    }

    /**
     * The claims of the assertion when it is a signed token of a key of the client; null otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function verifiedClaims(Client $client, #[SensitiveParameter] string $assertion): ?array
    {
        try {
            return Jws::decode($assertion, static function (string $kid) use ($client): ?JWK {
                $key = $client->publicKey($kid);

                return null === $key ? null : new JWK($key);
            });
        } catch (MalformedInput|VerificationFailed) {
            return null;
        }
    }

    /**
     * Whether an optional time is absent, or an integer no later than the bound.
     */
    private function notAhead(mixed $time, int $bound): bool
    {
        return null === $time || \is_int($time) && $time <= $bound;
    }
}
