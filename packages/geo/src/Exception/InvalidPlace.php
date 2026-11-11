<?php

declare(strict_types=1);

namespace Phil\Geo\Exception;

use InvalidArgumentException;

/**
 * A place that cannot be described as given.
 */
final class InvalidPlace extends InvalidArgumentException implements GeoException
{
    private const int MAX_SHOWN = 64;

    public static function country(string $code): self
    {
        return new self(\sprintf('A country is its two letters of ISO 3166-1; "%s" is not.', $code));
    }

    /**
     * The refused value usually comes from the client: the message keeps its first {@see self::MAX_SHOWN} bytes, with
     * control characters escaped and invalid UTF-8 replaced by U+FFFD, so that a log line cannot be forged nor bloated.
     */
    public static function ip(string $ip): self
    {
        $shown = json_encode(substr($ip, 0, self::MAX_SHOWN), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR);

        return new self(\sprintf('%s is not an IP address.', $shown));
    }
}
