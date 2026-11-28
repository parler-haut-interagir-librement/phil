<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use RuntimeException;
use Throwable;

/**
 * An address the client does not read: not an absolute HTTP or HTTPS address of a host name, a redirect to such an
 * address, a redirect from an HTTPS address to an HTTP one, or a host that is or resolves to a loopback, private,
 * link-local or reserved network.
 *
 * The message shows each address without its user information, query and fragment. A host refused for its network
 * does not keep the transport error that said so, which holds the whole address.
 */
final class RefusedAddress extends RuntimeException implements SafeHttpException
{
    public static function notPublic(string $address, ?Throwable $previous = null): self
    {
        return new self(\sprintf('"%s" is not an http or https address of a host name.', ShownAddress::of($address)), 0, $previous);
    }

    public static function redirect(string $location, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The redirect to "%s" is not an http or https address of a host name.', ShownAddress::of($location)), 0, $previous);
    }

    public static function downgrade(string $from, string $to): self
    {
        return new self(\sprintf('The redirect from "%s" to "%s" leaves https for http: its headers, body and answer would travel in clear text.', ShownAddress::of($from), ShownAddress::of($to)));
    }

    public static function privateNetwork(string $address, Throwable $previous): self
    {
        return new self(\sprintf('"%s" is or resolves to a private, loopback, link-local or reserved network.', ShownAddress::of($address)));
    }
}
