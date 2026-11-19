<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * A repository that gives everything to another and keeps the identifier of each conversation it is asked to save.
 */
final class SavingRepository implements ConversationRepository
{
    /** @var list<string> */
    public array $saved = [];

    public function __construct(private readonly ConversationRepository $inner)
    {
    }

    #[Override]
    public function find(ConversationId $id): ?Conversation
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function save(Conversation $conversation): void
    {
        $this->saved[] = $conversation->id->value;
        $this->inner->save($conversation);
    }

    #[Override]
    public function directBetween(ParticipantId $one, ParticipantId $other): ?Conversation
    {
        return $this->inner->directBetween($one, $other);
    }

    #[Override]
    public function participations(ConversationId $id): array
    {
        return $this->inner->participations($id);
    }

    #[Override]
    public function participation(ConversationId $id, ParticipantId $participant): ?Participation
    {
        return $this->inner->participation($id, $participant);
    }

    #[Override]
    public function saveParticipation(Participation $participation): void
    {
        $this->inner->saveParticipation($participation);
    }

    #[Override]
    public function add(Message $message): void
    {
        $this->inner->add($message);
    }

    #[Override]
    public function messages(ConversationId $id, int $limit, ?int $before = null): array
    {
        return $this->inner->messages($id, $limit, $before);
    }

    #[Override]
    public function inbox(ParticipantId $participant, int $limit, ?int $before = null): array
    {
        return $this->inner->inbox($participant, $limit, $before);
    }

    #[Override]
    public function unreadConversations(ParticipantId $participant): int
    {
        return $this->inner->unreadConversations($participant);
    }

    #[Override]
    public function conversationsOf(ParticipantId $participant): array
    {
        return $this->inner->conversationsOf($participant);
    }

    #[Override]
    public function messagesBy(ParticipantId $participant): array
    {
        return $this->inner->messagesBy($participant);
    }

    #[Override]
    public function forget(ParticipantId $participant): array
    {
        return $this->inner->forget($participant);
    }
}
