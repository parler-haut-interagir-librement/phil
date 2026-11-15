<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Token;

use Phil\Lti\Contract\AccessTokens;
use Phil\Lti\Contract\Nonces;
use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\LtiException;
use Phil\Lti\Exception\TokenRequestRefused;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Security\Random;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Scope;
use Psr\Clock\ClockInterface;

/**
 * The token endpoint of the platform: the grant `client_credentials`, the tool authenticated by a JWT assertion signed
 * with its key (RFC 7523) — `iss` and `sub` its client, `aud` the address of the endpoint, `exp` and `iat` holding, a
 * `jti` used once —, the scopes granted those it asks for that are known and registered for it. The token is a random
 * value given once and kept by its hash, for an hour by default.
 */
final readonly class TokenEndpoint
{
    public const string JTI_SCOPE = 'client_assertion';

    public const int DEFAULT_LIFETIME = 3600;

    public function __construct(
        private string $tokenUrl,
        private ToolRegistrations $tools,
        private AccessTokens $tokens,
        private Nonces $nonces,
        private JwtVerifier $verifier,
        private ClockInterface $clock,
        private int $lifetime = self::DEFAULT_LIFETIME,
    ) {
    }

    /**
     * @param array<array-key, mixed> $parameters the body of the request, form-encoded
     *
     * @throws TokenRequestRefused
     */
    public function issue(array $parameters): IssuedToken
    {
        if ('client_credentials' !== ($parameters['grant_type'] ?? null)) {
            throw TokenRequestRefused::unsupportedGrantType();
        }

        $assertion = $parameters['client_assertion'] ?? null;

        if (AccessTokenProvider::ASSERTION_TYPE !== ($parameters['client_assertion_type'] ?? null) || !\is_string($assertion)) {
            throw TokenRequestRefused::invalidRequest('The client authenticates by a JWT assertion.');
        }

        $requested = \is_string($parameters['scope'] ?? null) ? array_values(array_unique(array_filter(explode(' ', $parameters['scope']), static fn (string $scope): bool => '' !== $scope))) : [];

        try {
            $jwt = Jwt::parse($assertion);
            $clientId = $jwt->claims['iss'] ?? null;

            if (!\is_string($clientId) || ($jwt->claims['sub'] ?? null) !== $clientId) {
                throw TokenRequestRefused::invalidClient('The assertion is issued by its client, for itself.');
            }

            $tool = $this->tools->find($clientId) ?? throw TokenRequestRefused::invalidClient('The client is not registered.');
            $audience = $jwt->claims['aud'] ?? null;

            if ($audience !== $this->tokenUrl && (!\is_array($audience) || !\in_array($this->tokenUrl, $audience, true))) {
                throw TokenRequestRefused::invalidClient('The assertion is not addressed to this token endpoint.');
            }

            $jwt = $this->verifier->verify($assertion, $tool->keySetUrl);
            $jti = $jwt->claims['jti'] ?? null;

            if (!\is_string($jti) || '' === $jti || \strlen($jti) > 255 || !$this->nonces->use(self::JTI_SCOPE, $clientId."\n".$jti, $this->verifier->expiryOfNonce())) {
                throw TokenRequestRefused::invalidClient('The assertion has no identifier, or it was already used.');
            }
        } catch (InvalidToken $invalidToken) {
            throw TokenRequestRefused::invalidClient($invalidToken->getMessage());
        } catch (TokenRequestRefused $tokenRequestRefused) {
            throw $tokenRequestRefused;
        } catch (LtiException $ltiException) {
            throw TokenRequestRefused::invalidClient($ltiException->getMessage());
        }

        $granted = array_values(array_filter($requested, static fn (string $scope): bool => \in_array($scope, Scope::ALL, true) && $tool->grants($scope)));

        if ([] === $granted) {
            throw TokenRequestRefused::invalidScope('None of the scopes asked for is granted to the client.');
        }

        $value = Random::token();
        $this->tokens->save(new AccessToken(AccessToken::hash($value), $clientId, $granted, $this->clock->now()->modify(\sprintf('+%d seconds', $this->lifetime))));

        return new IssuedToken($value, $this->lifetime, $granted);
    }
}
