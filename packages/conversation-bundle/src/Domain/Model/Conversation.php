<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;

/**
 * A conversation: direct or a group, its subject — a group's, in the clear in memory and sealed at rest —, when it
 * started, and the sequence and moment of its last message, which order the inboxes. Moments are kept in UTC.
 */
final class Conversation
{
    private DateTimeImmutable $lastMessageAt;

    public function __construct(
        public readonly ConversationId $id,
        public readonly Kind $kind,
        private readonly ?string $subject,
        public readonly DateTimeImmutable $startedAt,
        ?DateTimeImmutable $lastMessageAt = null,
        private int $lastSequence = 0,
    ) {
        $this->lastMessageAt = ($lastMessageAt ?? $startedAt)->setTimezone(new DateTimeZone('UTC'));
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function lastMessageAt(): DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    /**
     * The sequence of the last message, 0 before the first.
     */
    public function lastSequence(): int
    {
        return $this->lastSequence;
    }

    public function isGroup(): bool
    {
        return Kind::Group === $this->kind;
    }

    /**
     * Notes a new message: its sequence and moment become the conversation's last.
     */
    public function received(Message $message): void
    {
        $this->lastSequence = max($this->lastSequence, $message->sequence());
        $this->lastMessageAt = $message->sentAt;
    }
}
