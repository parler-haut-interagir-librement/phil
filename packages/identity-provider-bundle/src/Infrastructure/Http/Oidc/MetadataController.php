<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequestParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The discovery document (OpenID Connect Discovery 1.0, RFC 8414) at `/.well-known/openid-configuration`, and the key
 * set at its `jwks_uri`, both public and cacheable for a short while, so that a rotation reaches clients quickly.
 */
final readonly class MetadataController
{
    public const string DISCOVERY = 'phil_identity_provider_oidc_discovery';

    public const string JWKS = 'phil_identity_provider_oidc_jwks';

    public function __construct(
        private ProviderSettings $settings,
        private SigningKeys $keys,
        private UrlGeneratorInterface $urls,
        private int $maxAge = 300,
    ) {
    }

    public function discovery(): JsonResponse
    {
        $url = fn (string $route): string => $this->urls->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->cacheable(new JsonResponse([
            'issuer' => $this->settings->issuer,
            'authorization_endpoint' => $url(AuthorizeController::AUTHORIZE),
            'token_endpoint' => $url(TokenController::TOKEN),
            'userinfo_endpoint' => $url(UserinfoController::USERINFO),
            'jwks_uri' => $url(self::JWKS),
            'end_session_endpoint' => $url(EndSessionController::END_SESSION),
            'revocation_endpoint' => $url(TokenController::REVOKE),
            'introspection_endpoint' => $url(TokenController::INTROSPECT),
            'scopes_supported' => $this->settings->scopes,
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => [$this->settings->algorithm->value],
            'token_endpoint_auth_methods_supported' => TokenController::AUTH_METHODS,
            'revocation_endpoint_auth_methods_supported' => TokenController::AUTH_METHODS,
            'introspection_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'amr', 'sid', 'email', 'email_verified', 'name', 'phone_number', 'phone_number_verified'],
            'prompt_values_supported' => AuthorizationRequestParser::PROMPTS,
            'authorization_response_iss_parameter_supported' => true,
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
            'claims_parameter_supported' => false,
        ]));
    }

    public function jwks(): JsonResponse
    {
        return $this->cacheable(new JsonResponse($this->keys->jwks()));
    }

    private function cacheable(JsonResponse $response): JsonResponse
    {
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
