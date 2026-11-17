<?php

declare(strict_types=1);

namespace Phil\Outbox\Exception;

use InvalidArgumentException;

/**
 * A setting of the relay, of its backoff or of the codecs out of its bounds.
 */
final class InvalidSetting extends InvalidArgumentException implements OutboxException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
