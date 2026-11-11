<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Refuses a form of the admin posted from another site, with the standard request headers rather than a token:
 * `Sec-Fetch-Site: same-origin` when the browser sends it, otherwise an `Origin` that is the admin's own. A
 * request that carries neither is refused, since nothing then says where it comes from.
 */
final class SameOriginGuard
{
    private function __construct()
    {
    }

    public static function assert(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        if (null !== $site) {
            if ('same-origin' !== $site) {
                throw new AccessDeniedHttpException('The form was not posted from this site.');
            }

            return;
        }

        if ($request->headers->get('Origin') !== $request->getSchemeAndHttpHost()) {
            throw new AccessDeniedHttpException('The form was not posted from this site.');
        }
    }
}
