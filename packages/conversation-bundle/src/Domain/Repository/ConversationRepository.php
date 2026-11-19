<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Repository;

use Phil\ConversationBundle\Domain\Exception\DirectConversationExists;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * Where conversations, their participants and their messages are kept. Subjects and bodies come in and go out in the
 * clear: the store seals them at rest.
 */
interface ConversationRepository
{
    public function find(ConversationId $id): ?Conversation;

    /**
     * Inserts the conversation, or updates its subject and last message.
     */
    public function save(Conversation $conversation): void;

    /**
     * The direct conversation between two participants, whether or not they left it.
     */
    public function directBetween(ParticipantId $one, ParticipantId $other): ?Conversation;

    /**
     * Every participation of the conversation, active or left, in the order they joined.
     *
     * @return list<Participation>
     */
    public function participations(ConversationId $id): array;

    public function participation(ConversationId $id, ParticipantId $participant): ?Participation;

    /**
     * Inserts the participation, or updates its reading, muting and departure.
     *
     * @throws DirectConversationExists when it completes a new direct conversation while its two participants already
     *                                  share another one; the new conversation is removed
     */
    public function saveParticipation(Participation $participation): void;

    /**
     * Keeps a message and numbers it: its sequence is set.
     */
    public function add(Message $message): void;

    /**
     * Up to `$limit` messages of the conversation, those before the sequence `$before` when it is given, the latest
     * ones, in the order they were sent.
     *
     * @param positive-int $limit
     *
     * @return list<Message>
     */
    public function messages(ConversationId $id, int $limit, ?int $before = null): array;

    /**
     * Up to `$limit` conversations the participant is active in, latest message first, those whose last sequence is
     * before `$before` when it is given.
     *
     * @param positive-int $limit
     *
     * @return list<InboxEntry>
     */
    public function inbox(ParticipantId $participant, int $limit, ?int $before = null): array;

    /**
     * How many of the participant's active conversations hold messages of the others the participant has not read.
     */
    public function unreadConversations(ParticipantId $participant): int;

    /**
     * Every conversation the participant took part in, active or left, with their participation.
     *
     * @return list<InboxEntry>
     */
    public function conversationsOf(ParticipantId $participant): array;

    /**
     * Every message the participant wrote.
     *
     * @return list<Message>
     */
    public function messagesBy(ParticipantId $participant): array;

    /**
     * Erases the participant: the body and author of each of their messages are wiped, their participations removed,
     * and the conversations left without anyone removed with their messages.
     *
     * @return array{messages: int, participations: int, conversations: int}
     */
    public function forget(ParticipantId $participant): array;
}
