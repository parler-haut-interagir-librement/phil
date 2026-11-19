<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * An identifier of a participant that is refused: empty, too long, with a space or a control character, or with an
 * `@`, the mark of an email address, which is never a stable identifier.
 */
final class InvalidParticipant extends InvalidArgumentException implements ConversationException
{
    public static function looksLikeAnEmail(): self
    {
        return new self('A participant is named by a stable identifier of the application, never by an email address: an identifier with "@" is refused.');
    }

    public static function malformed(int $maxLength): self
    {
        return new self(\sprintf('A participant identifier holds 1 to %d characters, without spaces nor control characters.', $maxLength));
    }
}
