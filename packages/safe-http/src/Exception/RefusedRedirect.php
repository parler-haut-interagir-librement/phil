<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use RuntimeException;

/**
 * A redirect answered to a request that does not follow one: a method other than `GET`, `HEAD` or `OPTIONS`, or a
 * request with a body, which a redirect would send a second time, perhaps to another host.
 */
final class RefusedRedirect extends RuntimeException implements SafeHttpException
{
    public static function forRequest(string $method, string $address, int $status): self
    {
        return new self(\sprintf('"%s" answered %s with the redirect %d, which a request with a body or a method other than GET, HEAD or OPTIONS does not follow.', mb_substr($address, 0, 100), $method, $status));
    }
}
