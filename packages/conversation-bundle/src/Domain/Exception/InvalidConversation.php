<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A conversation or a message the bundle refuses: an identifier that is not one, an empty or too long text, too many
 * or too few participants.
 */
final class InvalidConversation extends InvalidArgumentException implements ConversationException
{
    public static function identifier(string $what): self
    {
        return new self(\sprintf('The identifier of a %s is a UUID.', $what));
    }

    public static function emptyBody(): self
    {
        return new self('A message says something: its body is empty.');
    }

    public static function bodyTooLong(int $max): self
    {
        return new self(\sprintf('A message holds %d characters at most.', $max));
    }

    public static function subjectTooLong(int $max): self
    {
        return new self(\sprintf('The subject of a conversation holds %d characters at most.', $max));
    }

    public static function participants(int $max): self
    {
        return new self(\sprintf('A group conversation brings together 2 to %d participants, the author included.', $max));
    }

    public static function notAGroup(): self
    {
        return new self('Only a group conversation takes new participants.');
    }

    public static function withOneself(): self
    {
        return new self('A direct conversation is between two different participants.');
    }
}
