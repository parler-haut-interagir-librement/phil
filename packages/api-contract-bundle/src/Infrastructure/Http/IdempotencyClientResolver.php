<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * The API or OAuth client a request was sent by, once authenticated, for the scope of its idempotency keys: two
 * clients acting for one user never share a key. Null when the request names no client.
 */
interface IdempotencyClientResolver
{
    public function client(Request $request): ?string;
}
