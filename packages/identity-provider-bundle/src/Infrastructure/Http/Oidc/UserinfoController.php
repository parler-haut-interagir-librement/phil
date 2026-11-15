<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Oidc\Claims;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The userinfo endpoint (OpenID Connect Core, section 5.3), `GET` or `POST`: the access token in the `Authorization`
 * header with the Bearer scheme (RFC 6750, section 2.1) or, for a `POST`, in the `access_token` member of a form body
 * (section 2.2) — never in the query, never both —, the claims of its scopes in the answer.
 */
final readonly class UserinfoController
{
    public const string USERINFO = 'phil_identity_provider_oidc_userinfo';

    public function __construct(
        private Tokens $tokens,
    ) {
    }

    public function userinfo(Request $request): JsonResponse
    {
        try {
            [$account, $scopes] = $this->tokens->bearer($this->accessToken($request));
        } catch (OAuthError $oAuthError) {
            return OAuthResponses::error($oAuthError, bearerRealm: 'userinfo');
        }

        $response = OAuthResponses::json(Claims::of($account, $scopes));
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * @throws OAuthError `invalid_request` when the token comes both ways, `invalid_token` when it comes neither way
     */
    private function accessToken(Request $request): string
    {
        $header = $request->headers->get('Authorization');
        $body = $request->isMethod('POST') && 'form' === $request->getContentTypeFormat() ? $request->request->all()['access_token'] ?? null : null;

        if (null !== $header && null !== $body) {
            throw OAuthError::invalidRequest('The access token comes in the header or in the body, not both.');
        }

        if (\is_string($body) && '' !== $body) {
            return $body;
        }

        if (null === $header || !str_starts_with(strtolower($header), 'bearer ')) {
            throw OAuthError::invalidToken();
        }

        return trim(substr($header, 7));
    }
}
