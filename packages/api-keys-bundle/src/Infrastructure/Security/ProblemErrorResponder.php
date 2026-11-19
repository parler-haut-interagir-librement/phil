<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Security;

use Override;
use Phil\ApiContract\Problem\ProblemDetails;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemDetailsResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * `401 Unauthorized` as RFC 9457 problem details, with the challenge of RFC 6750: the entry point and the failure
 * handler of an API firewall when `phil/api-contract-bundle` is installed, in place of the plain text Symfony's
 * `access_token` authenticator answers. It never says why a token was refused.
 */
final readonly class ProblemErrorResponder implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface
{
    public function __construct(
        private string $realm,
    ) {
    }

    /**
     * A request without a token, on a route that requires a user: `Bearer realm="api"`.
     */
    #[Override]
    public function start(Request $request, ?AuthenticationException $authException = null): ProblemDetailsResponse
    {
        return $this->unauthorized(false);
    }

    /**
     * A token presented and refused: `Bearer realm="api",error="invalid_token"`.
     */
    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ProblemDetailsResponse
    {
        return $this->unauthorized(true);
    }

    private function unauthorized(bool $invalidToken): ProblemDetailsResponse
    {
        return new ProblemDetailsResponse(
            ProblemDetails::create(
                Response::HTTP_UNAUTHORIZED,
                title: 'Unauthorized',
                detail: $invalidToken ? 'The API token was refused.' : 'An API token is required.',
            ),
            ['WWW-Authenticate' => \sprintf('Bearer realm="%s"', addcslashes($this->realm, '"\\')).($invalidToken ? ',error="invalid_token"' : '')],
        );
    }
}
