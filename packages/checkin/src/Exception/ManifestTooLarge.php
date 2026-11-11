<?php

declare(strict_types=1);

namespace Phil\Checkin\Exception;

use RuntimeException;

/**
 * The catalog holds more credentials for a checkpoint than an offline manifest may carry.
 */
final class ManifestTooLarge extends RuntimeException implements CheckinException
{
    public static function over(int $limit): self
    {
        return new self(\sprintf('An offline manifest carries at most %d credentials; the catalog holds more for this checkpoint.', $limit));
    }
}
