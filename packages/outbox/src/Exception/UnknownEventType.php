<?php

declare(strict_types=1);

namespace Phil\Outbox\Exception;

use InvalidArgumentException;

/**
 * An event type that no codec rebuilds: the outbox records only the types of its allowlist.
 */
final class UnknownEventType extends InvalidArgumentException implements OutboxException
{
    public static function named(string $type): self
    {
        return new self(\sprintf('No codec rebuilds the event type "%s": register one before recording it.', mb_strimwidth($type, 0, 120, '…')));
    }
}
