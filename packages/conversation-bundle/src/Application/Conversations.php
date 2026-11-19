<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application;

use Override;
use Phil\ConversationBundle\Application\Contract\ConversationReader;
use Phil\ConversationBundle\Application\Event\ConversationStarted;
use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Exception\DirectConversationExists;
use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\Exception\NotAParticipant;
use Phil\ConversationBundle\Domain\Exception\WritingRefused;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The entry point of the conversations: starting one, direct or in a group, writing in it, reading it, the inbox and
 * its unread count, the muting and leaving. Every call that reads or writes for a participant checks that they take
 * part in the conversation; the reception settings of the recipients decide who may start one. Each message kept is
 * followed by `MessageSent`, each conversation started by `ConversationStarted`, both by identifiers only.
 */
final readonly class Conversations implements ConversationReader
{
    public function __construct(
        private ConversationRepository $repository,
        private Reception $reception,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private ConversationLimits $limits = new ConversationLimits(),
    ) {
    }

    /**
     * Writes to another participant, in the direct conversation of the two, started when they have none and taken up
     * again when one of them left it. When both start it at once, the later message goes in the conversation the
     * other started.
     *
     * @throws InvalidConversation when writing to oneself or the body is refused
     * @throws WritingRefused      when the recipient does not receive messages from the author
     */
    public function startDirect(ParticipantId $from, ParticipantId $to, string $body): Conversation
    {
        if ($from->equals($to)) {
            throw InvalidConversation::withOneself();
        }

        $body = $this->body($body);

        if (!$this->reception->mayStart($from, $to)) {
            throw WritingRefused::create();
        }

        $conversation = $this->repository->directBetween($from, $to);

        if ($conversation instanceof Conversation) {
            return $this->takeUp($conversation, $from, $body);
        }

        try {
            return $this->start(Kind::Direct, $from, [$to], null, $body);
        } catch (DirectConversationExists $directConversationExists) {
            // Another request started their conversation in the meantime: the message goes there.
            $conversation = $this->repository->directBetween($from, $to) ?? throw $directConversationExists;

            return $this->takeUp($conversation, $from, $body);
        }
    }

    /**
     * Starts a group conversation with other participants, its subject optional.
     *
     * @param list<ParticipantId> $others
     *
     * @throws InvalidConversation when the group is too small or too large, or the subject or body is refused
     * @throws WritingRefused      when one of the others does not receive messages from the author
     */
    public function startGroup(ParticipantId $from, array $others, ?string $subject, string $body): Conversation
    {
        $others = $this->others($from, $others);

        if ([] === $others || \count($others) + 1 > $this->limits->groupSize) {
            throw InvalidConversation::participants($this->limits->groupSize);
        }

        $subject = $this->subject($subject);
        $body = $this->body($body);

        foreach ($others as $other) {
            if (!$this->reception->mayStart($from, $other)) {
                throw WritingRefused::create();
            }
        }

        return $this->start(Kind::Group, $from, $others, $subject, $body);
    }

    /**
     * Starts a group conversation with its author alone, that a package built on the bundle brings others into with
     * `join()`: a request to a help desk, a chat with a visitor.
     *
     * @throws InvalidConversation when the subject or body is refused
     */
    public function startOpen(ParticipantId $from, ?string $subject, string $body): Conversation
    {
        return $this->start(Kind::Group, $from, [], $this->subject($subject), $this->body($body));
    }

    /**
     * Writes in a conversation the author takes part in. In a direct conversation, a block either way refuses the
     * message, and the other participant gets the conversation back if they left it.
     *
     * @throws NotAParticipant     when the author is not an active participant
     * @throws InvalidConversation when the body is refused
     * @throws WritingRefused      when the other participant of a direct conversation blocked the author, or the reverse
     */
    public function reply(ConversationId $id, ParticipantId $author, string $body): Message
    {
        $conversation = $this->open($id, $author);
        $body = $this->body($body);
        $participations = $this->repository->participations($id);

        if (!$conversation->isGroup()) {
            foreach ($participations as $participation) {
                if ($participation->participant->equals($author)) {
                    continue;
                }

                if ($this->reception->blockedEitherWay($author, $participation->participant)) {
                    throw WritingRefused::create();
                }

                if (!$participation->isActive()) {
                    $participation->rejoin();
                    $this->repository->saveParticipation($participation);
                }
            }
        }

        return $this->post($conversation, $author, $body, $participations);
    }

    /**
     * Brings a participant into a group conversation, or back into it. Named by an active participant, the reception
     * settings of the newcomer apply; without one, the call is the application's or a package's own — an agent joining
     * a request — and they do not.
     *
     * @throws NotAParticipant     when the conversation does not exist, or `$by` is not an active participant
     * @throws InvalidConversation when the conversation is direct, or the group is full
     * @throws WritingRefused      when the newcomer does not receive messages from `$by`
     */
    public function join(ConversationId $id, ParticipantId $who, ?ParticipantId $by = null): void
    {
        $conversation = $by instanceof ParticipantId ? $this->open($id, $by) : $this->repository->find($id);

        if (!$conversation instanceof Conversation) {
            throw NotAParticipant::of($id->value);
        }

        if (!$conversation->isGroup()) {
            throw InvalidConversation::notAGroup();
        }

        if ($by instanceof ParticipantId && !$this->reception->mayStart($by, $who)) {
            throw WritingRefused::create();
        }

        $participations = $this->repository->participations($id);
        $active = array_filter($participations, static fn (Participation $participation): bool => $participation->isActive());
        $existing = array_find($participations, static fn (Participation $participation): bool => $participation->participant->equals($who));

        if ($existing instanceof Participation && $existing->isActive()) {
            return;
        }

        if (\count($active) + 1 > $this->limits->groupSize) {
            throw InvalidConversation::participants($this->limits->groupSize);
        }

        if ($existing instanceof Participation) {
            $existing->rejoin();
            $this->repository->saveParticipation($existing);

            return;
        }

        $this->repository->saveParticipation(new Participation($id, $who, $this->clock->now()));
    }

    /**
     * The conversation, for one of its active participants.
     *
     * @throws NotAParticipant when it does not exist, or the reader is not an active participant
     */
    public function open(ConversationId $id, ParticipantId $reader): Conversation
    {
        $conversation = $this->repository->find($id);
        $participation = $this->repository->participation($id, $reader);

        if (!$conversation instanceof Conversation || !$participation instanceof Participation || !$participation->isActive()) {
            throw NotAParticipant::of($id->value);
        }

        return $conversation;
    }

    /**
     * A page of the messages of a conversation, for one of its active participants: the latest ones, or those before a
     * sequence, in the order they were sent.
     *
     * @return list<Message>
     *
     * @throws NotAParticipant when the reader is not an active participant
     */
    public function thread(ConversationId $id, ParticipantId $reader, ?int $limit = null, ?int $before = null): array
    {
        $this->open($id, $reader);

        return $this->repository->messages($id, $this->limits->page($limit), $before);
    }

    /**
     * Notes that the reader read the conversation up to its last message.
     *
     * @throws NotAParticipant when the reader is not an active participant
     */
    public function markRead(ConversationId $id, ParticipantId $reader): void
    {
        $conversation = $this->open($id, $reader);
        $participation = $this->participation($id, $reader);
        $participation->read($conversation->lastSequence());

        $this->repository->saveParticipation($participation);
    }

    /**
     * Mutes the conversation for the participant, or lets it be heard again: a muted conversation notifies nothing.
     *
     * @throws NotAParticipant when the participant is not an active participant
     */
    public function mute(ConversationId $id, ParticipantId $participant, bool $muted = true): void
    {
        $this->open($id, $participant);
        $participation = $this->participation($id, $participant);
        $participation->mute($muted);

        $this->repository->saveParticipation($participation);
    }

    /**
     * Leaves the conversation: it leaves the inbox, and its new messages are not received. A direct conversation comes
     * back when the other participant writes again.
     *
     * @throws NotAParticipant when the participant is not an active participant
     */
    public function leave(ConversationId $id, ParticipantId $participant): void
    {
        $this->open($id, $participant);
        $participation = $this->participation($id, $participant);
        $participation->leave($this->clock->now());

        $this->repository->saveParticipation($participation);
    }

    /**
     * A page of the participant's inbox, latest message first.
     *
     * @return list<InboxEntry>
     */
    public function inbox(ParticipantId $participant, ?int $limit = null, ?int $before = null): array
    {
        return $this->repository->inbox($participant, $this->limits->page($limit), $before);
    }

    /**
     * How many conversations hold messages the participant has not read.
     */
    public function unreadConversations(ParticipantId $participant): int
    {
        return $this->repository->unreadConversations($participant);
    }

    #[Override]
    public function conversation(ConversationId $id): ?Conversation
    {
        return $this->repository->find($id);
    }

    #[Override]
    public function messages(ConversationId $id, int $limit, ?int $before = null): array
    {
        return $this->repository->messages($id, $this->limits->page($limit), $before);
    }

    #[Override]
    public function participants(ConversationId $id): array
    {
        return $this->repository->participations($id);
    }

    /**
     * Writes in the direct conversation two participants share, bringing back the one who left it.
     */
    private function takeUp(Conversation $conversation, ParticipantId $from, string $body): Conversation
    {
        $participations = $this->repository->participations($conversation->id);

        foreach ($participations as $participation) {
            if (!$participation->isActive()) {
                $participation->rejoin();
                $this->repository->saveParticipation($participation);
            }
        }

        $this->post($conversation, $from, $body, $participations);

        return $conversation;
    }

    /**
     * @param list<ParticipantId> $others
     */
    private function start(Kind $kind, ParticipantId $from, array $others, ?string $subject, string $body): Conversation
    {
        $now = $this->clock->now();
        $conversation = new Conversation(ConversationId::generate(), $kind, $subject, $now);
        $this->repository->save($conversation);

        $participations = [];

        foreach ([$from, ...$others] as $participant) {
            $participation = new Participation($conversation->id, $participant, $now);
            $this->repository->saveParticipation($participation);
            $participations[] = $participation;
        }

        $this->events?->dispatch(new ConversationStarted(
            $conversation->id->value,
            $from->value,
            array_map(static fn (ParticipantId $other): string => $other->value, $others),
        ));
        $this->post($conversation, $from, $body, $participations);

        return $conversation;
    }

    /**
     * @param list<Participation> $participations
     */
    private function post(Conversation $conversation, ParticipantId $author, string $body, array $participations): Message
    {
        $message = new Message(MessageId::generate(), $conversation->id, $author, $body, $this->clock->now());
        $this->repository->add($message);
        $conversation->received($message);
        $this->repository->save($conversation);

        $recipients = [];
        $notified = [];

        foreach ($participations as $participation) {
            if ($participation->participant->equals($author)) {
                $participation->read($message->sequence());
                $this->repository->saveParticipation($participation);

                continue;
            }

            if (!$participation->isActive()) {
                continue;
            }

            $recipients[] = $participation->participant->value;

            if (!$participation->isMuted() && !$this->reception->settings($participation->participant)->hasBlocked($author)) {
                $notified[] = $participation->participant->value;
            }
        }

        $this->events?->dispatch(new MessageSent($conversation->id->value, $message->id->value, $message->sequence(), $author->value, $recipients, $notified));

        return $message;
    }

    private function participation(ConversationId $id, ParticipantId $participant): Participation
    {
        return $this->repository->participation($id, $participant) ?? throw NotAParticipant::of($id->value);
    }

    /**
     * @param list<ParticipantId> $others
     *
     * @return list<ParticipantId>
     */
    private function others(ParticipantId $from, array $others): array
    {
        $unique = [];

        foreach ($others as $other) {
            if (!$other->equals($from)) {
                $unique[$other->value] = $other;
            }
        }

        return array_values($unique);
    }

    private function body(string $body): string
    {
        $body = trim($body);

        if ('' === $body) {
            throw InvalidConversation::emptyBody();
        }

        if (mb_strlen($body) > $this->limits->bodyLength) {
            throw InvalidConversation::bodyTooLong($this->limits->bodyLength);
        }

        return $body;
    }

    private function subject(?string $subject): ?string
    {
        $subject = trim($subject ?? '');

        if (mb_strlen($subject) > $this->limits->subjectLength) {
            throw InvalidConversation::subjectTooLong($this->limits->subjectLength);
        }

        return '' === $subject ? null : $subject;
    }
}
