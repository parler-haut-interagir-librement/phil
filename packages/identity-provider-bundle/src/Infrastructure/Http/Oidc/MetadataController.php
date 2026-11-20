<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequestParser;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;
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
        private bool $backchannelLogout = false,
    ) {
    }

    public function discovery(): JsonResponse
    {
        $url = fn (string $route): string => $this->urls->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
        $methods = array_map(static fn (ClientAuthMethod $method): string => $method->value, $this->settings->authMethods);
        $confidential = array_values(array_diff($methods, [ClientAuthMethod::None->value]));
        $optional = array_keys(array_filter(['acr' => null !== $this->settings->acr, 'idp' => null !== $this->settings->idp, 'session_start' => $this->settings->sessionStart]));

        return $this->cacheable(new JsonResponse(array_filter([
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
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => [SubjectType::Pairwise === $this->settings->subjectType ? 'pairwise' : 'public'],
            'id_token_signing_alg_values_supported' => [$this->settings->algorithm->value],
            'token_endpoint_auth_methods_supported' => $methods,
            'token_endpoint_auth_signing_alg_values_supported' => \in_array(ClientAuthMethod::PrivateKeyJwt, $this->settings->authMethods, true) ? SigningAlgorithm::cases() : null,
            'revocation_endpoint_auth_methods_supported' => $methods,
            'introspection_endpoint_auth_methods_supported' => $confidential,
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'amr', 'sid', ...$optional, 'email', 'email_verified', 'name', 'phone_number', 'phone_number_verified'],
            'acr_values_supported' => null === $this->settings->acr ? null : [$this->settings->acr],
            'prompt_values_supported' => AuthorizationRequestParser::PROMPTS,
            'authorization_response_iss_parameter_supported' => true,
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
            'claims_parameter_supported' => false,
            'backchannel_logout_supported' => $this->backchannelLogout ? true : null,
            'backchannel_logout_session_supported' => $this->backchannelLogout ? true : null,
        ], static fn (mixed $value): bool => null !== $value && [] !== $value)));
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
