<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use RuntimeException;
use Throwable;

/**
 * An address the client does not read: not an absolute HTTP or HTTPS address of a host name, a redirect to such an
 * address, or a host that is or resolves to a loopback, private, link-local or reserved network.
 */
final class RefusedAddress extends RuntimeException implements SafeHttpException
{
    public static function notPublic(string $address, ?Throwable $previous = null): self
    {
        return new self(\sprintf('"%s" is not an http or https address of a host name.', mb_substr($address, 0, 100)), 0, $previous);
    }

    public static function redirect(string $location, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The redirect to "%s" is not an http or https address of a host name.', mb_substr($location, 0, 100)), 0, $previous);
    }

    public static function privateNetwork(string $address, Throwable $previous): self
    {
        return new self(\sprintf('"%s" is or resolves to a private, loopback, link-local or reserved network.', mb_substr($address, 0, 100)), 0, $previous);
    }
}
