<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The issuer's profile, at its identifier, and its public keys as a JSON Web Key Set: what anyone needs to verify the
 * credentials the application issues. Both may be kept `$maxAge` seconds by browsers and shared caches, and are a `304`
 * to a request that already holds them.
 */
final readonly class IssuerController
{
    public const string PROFILE_ROUTE = 'phil_open_badges_issuer';

    public const string KEYS_ROUTE = 'phil_open_badges_issuer_keys';

    /**
     * @param array<string, mixed>                     $profile
     * @param array{keys: list<array<string, string>>} $keys
     */
    public function __construct(
        private array $profile,
        private array $keys,
        private int $maxAge,
    ) {
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->json($request, $this->profile, 'application/ld+json');
    }

    public function keys(Request $request): JsonResponse
    {
        return $this->json($request, $this->keys, 'application/jwk-set+json');
    }

    /**
     * @param array<string, mixed> $document
     */
    private function json(Request $request, array $document, string $type): JsonResponse
    {
        $response = new JsonResponse($document, Response::HTTP_OK, ['Content-Type' => $type]);
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
