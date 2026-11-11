<?php

declare(strict_types=1);

namespace Phil\Checkin\Exception;

use InvalidArgumentException;

/**
 * A batch of offline scans sent by a device is not one: not a list, too long, or a scan of it malformed. The message
 * names the position of the scan and what is wrong with it, never the value sent.
 */
final class InvalidOfflineBatch extends InvalidArgumentException implements CheckinException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function at(int $index, string $reason): self
    {
        return new self(\sprintf('Scan %d of the batch: %s', $index, $reason));
    }
}
