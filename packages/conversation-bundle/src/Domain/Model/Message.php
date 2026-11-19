<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * A message of a conversation: its author and body, both null once the author's data is erased, the moment it was
 * sent, in UTC, and its sequence, which the store gives when it keeps the message and which orders a conversation.
 */
final class Message
{
    public readonly DateTimeImmutable $sentAt;

    public function __construct(
        public readonly MessageId $id,
        public readonly ConversationId $conversation,
        public readonly ?ParticipantId $author,
        public readonly ?string $body,
        DateTimeImmutable $sentAt,
        private int $sequence = 0,
    ) {
        $this->sentAt = $sentAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The order of the message among all those of the store, 0 until it is kept.
     */
    public function sequence(): int
    {
        return $this->sequence;
    }

    /**
     * Set by the store, once, when it keeps the message.
     */
    public function numbered(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function isErased(): bool
    {
        return !$this->author instanceof ParticipantId;
    }

    public function isBy(ParticipantId $participant): bool
    {
        return $this->author instanceof ParticipantId && $this->author->equals($participant);
    }
}
