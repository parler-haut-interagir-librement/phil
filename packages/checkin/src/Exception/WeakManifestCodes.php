<?php

declare(strict_types=1);

namespace Phil\Checkin\Exception;

use RuntimeException;

/**
 * The catalog holds, for a checkpoint, a code too short or too simple for an offline manifest: whoever holds the
 * manifest, and so its key, could try every code of that shape against the fingerprints.
 */
final class WeakManifestCodes extends RuntimeException implements CheckinException
{
    public static function under(int $bits): self
    {
        return new self(\sprintf('An offline manifest carries codes of at least %d bits, whose fingerprints cannot be tried one by one; the catalog holds a weaker one for this checkpoint.', $bits));
    }
}
