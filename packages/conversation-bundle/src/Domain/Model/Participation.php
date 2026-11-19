<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * A participant's place in a conversation: since when, up to which message they read, whether they muted it, and
 * when they left it. Moments are kept in UTC.
 */
final class Participation
{
    public readonly DateTimeImmutable $joinedAt;

    private ?DateTimeImmutable $leftAt;

    public function __construct(
        public readonly ConversationId $conversation,
        public readonly ParticipantId $participant,
        DateTimeImmutable $joinedAt,
        private int $readUpTo = 0,
        private bool $muted = false,
        ?DateTimeImmutable $leftAt = null,
    ) {
        $this->joinedAt = $joinedAt->setTimezone(new DateTimeZone('UTC'));
        $this->leftAt = $leftAt?->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The sequence of the last message the participant read, 0 when none.
     */
    public function readUpTo(): int
    {
        return $this->readUpTo;
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }

    public function leftAt(): ?DateTimeImmutable
    {
        return $this->leftAt;
    }

    public function isActive(): bool
    {
        return !$this->leftAt instanceof DateTimeImmutable;
    }

    /**
     * Notes that the participant read up to a message; a later reading never moves the mark back.
     */
    public function read(int $sequence): void
    {
        $this->readUpTo = max($this->readUpTo, $sequence);
    }

    public function mute(bool $muted): void
    {
        $this->muted = $muted;
    }

    public function leave(DateTimeImmutable $at): void
    {
        $this->leftAt ??= $at->setTimezone(new DateTimeZone('UTC'));
    }

    public function rejoin(): void
    {
        $this->leftAt = null;
    }
}
