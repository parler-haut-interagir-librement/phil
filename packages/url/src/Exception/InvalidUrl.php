<?php

declare(strict_types=1);

namespace Phil\Url\Exception;

use InvalidArgumentException;

/**
 * A text that is not a web address, or an address that is not of the type it is given.
 */
final class InvalidUrl extends InvalidArgumentException implements UrlException
{
    public static function notAUrl(string $text): self
    {
        return new self(\sprintf('"%s" is not an http or https address.', mb_substr($text, 0, 100)));
    }

    public static function credentials(): self
    {
        return new self('A web address carries no user name nor password.');
    }

    public static function host(string $host): self
    {
        return new self(\sprintf('"%s" is not a host name.', mb_substr($host, 0, 100)));
    }

    public static function port(int $port): self
    {
        return new self(\sprintf('%d is not a port a web address can name.', $port));
    }

    public static function tooLong(int $length): self
    {
        return new self(\sprintf('A web address is 2048 characters at most; this one is %d.', $length));
    }

    public static function notOfType(string $url, string $type): self
    {
        return new self(\sprintf('%s is not a %s address.', $url, $type));
    }
}
