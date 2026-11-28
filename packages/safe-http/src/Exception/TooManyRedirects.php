<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use RuntimeException;

/**
 * A chain of redirects longer than the limits allow.
 */
final class TooManyRedirects extends RuntimeException implements SafeHttpException
{
    public static function after(int $redirects, string $address): self
    {
        return new self(\sprintf('"%s" redirects more than %d times.', ShownAddress::of($address), $redirects));
    }
}
