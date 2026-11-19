<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Model;

use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * What a participant receives: who may start a conversation with them, and whom they blocked — at most
 * `MAX_BLOCKS` people, who can neither start a conversation with them nor write to them in a direct one.
 */
final class ReceptionSettings
{
    public const int MAX_BLOCKS = 1000;

    /** @var array<string, ParticipantId> */
    private array $blocked = [];

    /**
     * @param list<ParticipantId> $blocked
     */
    public function __construct(
        public readonly ParticipantId $participant,
        private WhoCanWrite $whoCanWrite = WhoCanWrite::Everyone,
        array $blocked = [],
    ) {
        foreach ($blocked as $other) {
            $this->blocked[$other->value] = $other;
        }
    }

    public function whoCanWrite(): WhoCanWrite
    {
        return $this->whoCanWrite;
    }

    /**
     * @return list<ParticipantId>
     */
    public function blocked(): array
    {
        return array_values($this->blocked);
    }

    public function hasBlocked(ParticipantId $other): bool
    {
        return isset($this->blocked[$other->value]);
    }

    public function allow(WhoCanWrite $whoCanWrite): void
    {
        $this->whoCanWrite = $whoCanWrite;
    }

    /**
     * @throws InvalidParticipant when blocking oneself, or one more person past the limit
     */
    public function block(ParticipantId $other): void
    {
        if ($other->equals($this->participant)) {
            throw new InvalidParticipant('A participant does not block themselves.');
        }

        if (!isset($this->blocked[$other->value]) && \count($this->blocked) >= self::MAX_BLOCKS) {
            throw new InvalidParticipant(\sprintf('A participant blocks %d people at most.', self::MAX_BLOCKS));
        }

        $this->blocked[$other->value] = $other;
    }

    public function unblock(ParticipantId $other): void
    {
        unset($this->blocked[$other->value]);
    }
}
