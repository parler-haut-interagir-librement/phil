<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Infrastructure\Http\Pages;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The JSON answers of the provider's endpoints: never cached, and an error with its code, its description and its
 * status — a `401` of a client authenticated by HTTP Basic carrying the challenge.
 */
final class OAuthResponses
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        return Pages::noStore($response);
    }

    public static function error(OAuthError $error, bool $basic = false, ?string $bearerRealm = null): JsonResponse
    {
        $response = self::json($error->toArray(), $error->status);

        if (401 === $error->status && $basic) {
            $response->headers->set('WWW-Authenticate', 'Basic realm="token"');
        }

        if (null !== $bearerRealm) {
            $response->headers->set('WWW-Authenticate', \sprintf('Bearer realm="%s", error="%s"', $bearerRealm, $error->error));
        }

        return $response;
    }
}
