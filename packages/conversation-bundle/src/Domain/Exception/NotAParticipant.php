<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use RuntimeException;

/**
 * A conversation read or written by someone who is not one of its active participants, or that does not exist: both
 * say the same, so that nobody learns which conversations exist.
 */
final class NotAParticipant extends RuntimeException implements ConversationException
{
    public static function of(string $conversation): self
    {
        return new self(\sprintf('No conversation "%s" among those of this participant.', $conversation));
    }
}
