<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The token endpoint (RFC 6749, section 3.2), the revocation endpoint (RFC 7009) and the introspection endpoint
 * (RFC 7662), `POST` with a form body, each authenticating its client first. Introspection is for confidential
 * clients — resource servers —; a public client gets `invalid_client`.
 */
final readonly class TokenController
{
    public const string TOKEN = 'phil_identity_provider_oidc_token';

    public const string REVOKE = 'phil_identity_provider_oidc_revoke';

    public const string INTROSPECT = 'phil_identity_provider_oidc_introspect';

    public const array AUTH_METHODS = ['client_secret_basic', 'client_secret_post', 'none'];

    public function __construct(
        private Tokens $tokens,
    ) {
    }

    public function token(Request $request): JsonResponse
    {
        $basic = false;

        try {
            $credentials = ClientCredentials::from($request);
            $basic = $credentials->basic;
            $client = $this->tokens->authenticateClient($credentials->clientId, $credentials->secret);

            return OAuthResponses::json(match ($request->request->all()['grant_type'] ?? null) {
                'authorization_code' => $this->tokens->exchangeCode($client, $this->required($request, 'code'), $this->required($request, 'redirect_uri'), $this->optional($request, 'code_verifier')),
                'refresh_token' => $this->tokens->refresh($client, $this->required($request, 'refresh_token'), $this->optional($request, 'scope')),
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
            $this->tokens->revoke($this->tokens->authenticateClient($credentials->clientId, $credentials->secret), $this->required($request, 'token'));
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
            $client = $this->tokens->authenticateClient($credentials->clientId, $credentials->secret);
            $this->assertConfidential($client);

            return OAuthResponses::json($this->tokens->introspect($client, $this->required($request, 'token')));
        } catch (OAuthError $oAuthError) {
            return OAuthResponses::error($oAuthError, $basic);
        }
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

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
