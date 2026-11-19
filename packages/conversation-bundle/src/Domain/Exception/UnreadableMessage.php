<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * A sealed text that does not open: not in the bundle's format, altered, moved to another row, or sealed under a master
 * key the key management service no longer holds.
 */
final class UnreadableMessage extends RuntimeException implements ConversationException
{
    public static function of(string $context, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The sealed text of %s does not open.', $context), previous: $previous);
    }
}
