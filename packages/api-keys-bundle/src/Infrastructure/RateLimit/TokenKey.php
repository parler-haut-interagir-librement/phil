<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\RateLimit;

use Override;
use Phil\ApiKeysBundle\Infrastructure\Security\ApiTokenHandler;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Who a request counts for in a limiter of `phil/rate-limit-bundle` — its own or one that counts on a meter of
 * `phil/usage-meter-bundle`: `api_token:` and the identifier of the token that authenticated it. A request
 * authenticated otherwise has no key, and the limiter does not count it.
 */
final readonly class TokenKey implements KeyResolver
{
    public const string PREFIX = 'api_token:';

    #[Override]
    public function keyOf(Request $request): ?string
    {
        $id = $request->attributes->get(ApiTokenHandler::TOKEN_ID_ATTRIBUTE);

        return \is_string($id) ? self::PREFIX.$id : null;
    }
}
