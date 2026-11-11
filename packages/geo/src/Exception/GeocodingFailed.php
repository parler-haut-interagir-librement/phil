<?php

declare(strict_types=1);

namespace Phil\Geo\Exception;

use RuntimeException;
use Throwable;

/**
 * A provider that could not answer: unreachable, refusing the key, over its quota, or answering what it should not.
 * A provider that answers that it found nothing is no failure: it gives no place.
 */
final class GeocodingFailed extends RuntimeException implements GeoException
{
    public static function because(string $provider, string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The geocoding provider "%s" failed: %s', $provider, $reason), previous: $previous);
    }
}
