<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use Phil\Lti\Exception\TokenRequestRefused;
use Phil\Lti\Platform\Token\TokenEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token endpoint of the platform, as RFC 6749 answers: the token in JSON, never cached; an error with its code,
 * 401 for a client that does not authenticate and 400 otherwise.
 */
final readonly class TokenController
{
    public const string ROUTE = 'phil_lti_platform_token';

    public function __construct(
        private TokenEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            return new JsonResponse($this->endpoint->issue($request->request->all())->toArray(), Response::HTTP_OK, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
        } catch (TokenRequestRefused $tokenRequestRefused) {
            return new JsonResponse($tokenRequestRefused->toArray(), 'invalid_client' === $tokenRequestRefused->error ? Response::HTTP_UNAUTHORIZED : Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
        }
    }
}
