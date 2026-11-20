<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The token endpoint (RFC 6749, section 3.2), the revocation endpoint (RFC 7009) and the introspection endpoint
 * (RFC 7662), `POST` with a form body, each authenticating its client first. Introspection is for confidential
 * clients — resource servers —; a public client gets `invalid_client`. The token endpoint answers the grants
 * `authorization_code`, `refresh_token` and `client_credentials`, each with an optional `resource` (RFC 8707).
 */
final readonly class TokenController
{
    public const string TOKEN = 'phil_identity_provider_oidc_token';

    public const string REVOKE = 'phil_identity_provider_oidc_revoke';

    public const string INTROSPECT = 'phil_identity_provider_oidc_introspect';

    public function __construct(
        private Tokens $tokens,
        private ?UrlGeneratorInterface $urls = null,
    ) {
    }

    public function token(Request $request): JsonResponse
    {
        $basic = false;

        try {
            $credentials = ClientCredentials::from($request);
            $basic = $credentials->basic;
            $client = $this->authenticate($credentials);
            $resource = $this->resource($request);

            return OAuthResponses::json(match ($request->request->all()['grant_type'] ?? null) {
                'authorization_code' => $this->tokens->exchangeCode($client, $this->required($request, 'code'), $this->required($request, 'redirect_uri'), $this->optional($request, 'code_verifier'), $resource),
                'refresh_token' => $this->tokens->refresh($client, $this->required($request, 'refresh_token'), $this->optional($request, 'scope'), $resource),
                'client_credentials' => $this->tokens->clientCredentials($client, $this->optional($request, 'scope'), $resource),
                default => throw OAuthError::unsupportedGrantType(),
            });
        } catch (OAuthError $oAuthError) {
            return OAuthResponses::error($oAuthError, $basic);
        }
    }

    public function revoke(Request $request): JsonResponse
    {
        $basic = false;

        try {
            $credentials = ClientCredentials::from($request);
            $basic = $credentials->basic;
            $this->tokens->revoke($this->authenticate($credentials), $this->required($request, 'token'));
        } catch (OAuthError $oAuthError) {
            return OAuthResponses::error($oAuthError, $basic);
        }

        return OAuthResponses::json([]);
    }

    public function introspect(Request $request): JsonResponse
    {
        $basic = false;

        try {
            $credentials = ClientCredentials::from($request);
            $basic = $credentials->basic;
            $client = $this->authenticate($credentials);
            $this->assertConfidential($client);

            return OAuthResponses::json($this->tokens->introspect($client, $this->required($request, 'token')));
        } catch (OAuthError $oAuthError) {
            return OAuthResponses::error($oAuthError, $basic);
        }
    }

    /**
     * The client the credentials prove, a client assertion naming the issuer or this token endpoint at its origin.
     */
    private function authenticate(ClientCredentials $credentials): Client
    {
        $path = ClientAuthMethod::PrivateKeyJwt === $credentials->method ? $this->urls?->generate(self::TOKEN, [], UrlGeneratorInterface::ABSOLUTE_PATH) : null;

        return $this->tokens->authenticateClient($credentials->clientId, $credentials->secret, $credentials->method, $path);
    }

    /**
     * The one resource the request names, or null; several are refused, as the provider issues a token for one.
     */
    private function resource(Request $request): ?string
    {
        $value = $request->request->all()['resource'] ?? null;

        if (null !== $value && (!\is_string($value) || '' === $value || \strlen($value) > 2048)) {
            throw OAuthError::invalidTarget('The request names one resource, an absolute address.');
        }

        return $value;
    }

    private function assertConfidential(Client $client): void
    {
        if ($client->isPublic()) {
            throw OAuthError::invalidClient();
        }
    }

    private function required(Request $request, string $name): string
    {
        $value = $request->request->all()[$name] ?? null;

        return \is_string($value) && '' !== $value && \strlen($value) <= 4096 ? $value : throw OAuthError::invalidRequest(\sprintf('The parameter "%s" is required.', $name));
    }

    private function optional(Request $request, string $name): ?string
    {
        $value = $request->request->all()[$name] ?? null;

        if (\is_string($value) && \strlen($value) > 4096) {
            throw OAuthError::invalidRequest(\sprintf('The parameter "%s" is too long.', $name));
        }

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
