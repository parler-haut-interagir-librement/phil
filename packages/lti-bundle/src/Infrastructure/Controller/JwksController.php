<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use Phil\Lti\Contract\KeyStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * The key set of the application, for the other side to verify what it signs: public for five minutes in caches.
 */
final readonly class JwksController
{
    public const string ROUTE = 'phil_lti_jwks';

    public function __construct(
        private KeyStore $keys,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->keys->publicKeys()->toJson(), Response::HTTP_OK, ['Content-Type' => 'application/jwk-set+json', 'Cache-Control' => 'public, max-age=300']);
    }
}
