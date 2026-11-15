<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use InvalidArgumentException;

/**
 * A request `send()` does not make: a method that is no token, or one that tunnels or echoes, a header the client sets
 * itself or that could split the request, more headers or a larger body than the limits allow.
 *
 * Its message names a header, never its value.
 */
final class InvalidRequest extends InvalidArgumentException implements SafeHttpException
{
    public static function method(string $method): self
    {
        return new self(\sprintf('"%s" is not a method the client sends.', mb_substr($method, 0, 30)));
    }

    public static function headerName(string $name): self
    {
        return new self(\sprintf('"%s" is not a header name.', mb_substr($name, 0, 60)));
    }

    public static function reservedHeader(string $name): self
    {
        return new self(\sprintf('The %s header is set by the client or the transport, never by the request.', $name));
    }

    public static function headerValue(string $name): self
    {
        return new self(\sprintf('The value of the %s header is empty, or holds a line break or a control character.', $name));
    }

    public static function headersTooLarge(int $maxBytes, int $maxHeaders): self
    {
        return new self(\sprintf('The headers of the request pass %d bytes or %d headers.', $maxBytes, $maxHeaders));
    }

    public static function bodyTooLarge(int $bytes, int $maxBytes): self
    {
        return new self(\sprintf('The body of the request is %d bytes; %d at most.', $bytes, $maxBytes));
    }
}
