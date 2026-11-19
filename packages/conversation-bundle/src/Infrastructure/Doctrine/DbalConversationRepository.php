<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Exception\DirectConversationExists;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\Repository\ConversationRepository;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\InboxEntry;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The conversations kept through DBAL, without the ORM, so that a long conversation or a full inbox is read by page:
 * subjects and bodies sealed by the `MessageSealer`, each bound to its row, the name of its master key kept beside it;
 * moments in UTC. A direct conversation carries a key made of the hash of its two participants, unique, so that two
 * participants share a single direct conversation. When two requests start it at once, the later one finds the key
 * taken: it removes the conversation it was writing and says so, under a savepoint when a transaction is open.
 */
final readonly class DbalConversationRepository implements ConversationRepository
{
    private const string COLUMNS = 'c.id, c.kind, c.subject, c.started_at, c.last_message_at, c.last_sequence';

    private const string PARTICIPATION = 'p.conversation_id, p.participant, p.joined_at, p.read_up_to, p.muted, p.left_at';

    public function __construct(
        private Connection $connection,
        private MessageSealer $sealer,
    ) {
    }

    #[Override]
    public function find(ConversationId $id): ?Conversation
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.ConversationTables::CONVERSATION.' c WHERE c.id = ?', [$id->value]);

        return false === $row ? null : $this->conversation($row);
    }

    #[Override]
    public function save(Conversation $conversation): void
    {
        $subject = $conversation->subject();
        $values = [
            'subject' => null === $subject ? null : $this->sealer->seal($subject, self::subjectContext($conversation->id)),
            'subject_key' => null === $subject ? null : $this->sealer->currentKey(),
        ];

        if (!$this->exists($conversation->id)) {
            $this->connection->insert(ConversationTables::CONVERSATION, $values + [
                'id' => $conversation->id->value,
                'kind' => $conversation->kind->value,
                'direct_key' => null,
                'started_at' => $conversation->startedAt,
                'last_message_at' => $conversation->lastMessageAt(),
                'last_sequence' => $conversation->lastSequence(),
            ], ['started_at' => Types::DATETIME_UTC_IMMUTABLE, 'last_message_at' => Types::DATETIME_UTC_IMMUTABLE]);
        } else {
            $this->connection->update(ConversationTables::CONVERSATION, $values, ['id' => $conversation->id->value]);
            // Two messages written at once: the later sequence stays the last, whichever request saves first.
            $this->connection->executeStatement(
                'UPDATE '.ConversationTables::CONVERSATION.' SET last_sequence = ?, last_message_at = ? WHERE id = ? AND last_sequence < ?',
                [$conversation->lastSequence(), $conversation->lastMessageAt(), $conversation->id->value, $conversation->lastSequence()],
                [ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING, ParameterType::INTEGER],
            );
        }
    }

    #[Override]
    public function directBetween(ParticipantId $one, ParticipantId $other): ?Conversation
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.ConversationTables::CONVERSATION.' c WHERE c.direct_key = ?', [self::directKey($one, $other)]);

        return false === $row ? null : $this->conversation($row);
    }

    #[Override]
    public function participations(ConversationId $id): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT '.self::PARTICIPATION.' FROM '.ConversationTables::PARTICIPANT.' p WHERE p.conversation_id = ? ORDER BY p.joined_at, p.participant', [$id->value]);

        return array_map($this->participationOf(...), $rows);
    }

    #[Override]
    public function participation(ConversationId $id, ParticipantId $participant): ?Participation
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::PARTICIPATION.' FROM '.ConversationTables::PARTICIPANT.' p WHERE p.conversation_id = ? AND p.participant = ?', [$id->value, $participant->value]);

        return false === $row ? null : $this->participationOf($row);
    }

    #[Override]
    public function saveParticipation(Participation $participation): void
    {
        $key = ['conversation_id' => $participation->conversation->value, 'participant' => $participation->participant->value];
        $values = ['read_up_to' => $participation->readUpTo(), 'muted' => $participation->isMuted(), 'left_at' => $participation->leftAt()];
        $types = ['muted' => Types::BOOLEAN, 'left_at' => Types::DATETIME_UTC_IMMUTABLE, 'joined_at' => Types::DATETIME_UTC_IMMUTABLE];

        if (0 === $this->connection->update(ConversationTables::PARTICIPANT, $values, $key, $types) && !$this->participation($participation->conversation, $participation->participant) instanceof Participation) {
            $this->connection->insert(ConversationTables::PARTICIPANT, $key + $values + ['joined_at' => $participation->joinedAt], $types);
            $this->direct($participation->conversation);
        }
    }

    #[Override]
    public function add(Message $message): void
    {
        $this->connection->insert(ConversationTables::MESSAGE, [
            'id' => $message->id->value,
            'conversation_id' => $message->conversation->value,
            'author' => $message->author?->value,
            'body' => null === $message->body ? null : $this->sealer->seal($message->body, self::messageContext($message->id)),
            'body_key' => null === $message->body ? null : $this->sealer->currentKey(),
            'sent_at' => $message->sentAt,
        ], ['sent_at' => Types::DATETIME_UTC_IMMUTABLE]);

        $message->numbered(self::integer($this->connection->lastInsertId()));
    }

    #[Override]
    public function messages(ConversationId $id, int $limit, ?int $before = null): array
    {
        $sql = 'SELECT seq, id, conversation_id, author, body, sent_at FROM '.ConversationTables::MESSAGE.' WHERE conversation_id = ?';
        $parameters = [$id->value];
        $types = [ParameterType::STRING];

        if (null !== $before) {
            $sql .= ' AND seq < ?';
            $parameters[] = $before;
            $types[] = ParameterType::INTEGER;
        }

        $rows = $this->connection->fetchAllAssociative($this->connection->getDatabasePlatform()->modifyLimitQuery($sql.' ORDER BY seq DESC', $limit), $parameters, $types);

        return array_reverse(array_map($this->message(...), $rows));
    }

    #[Override]
    public function inbox(ParticipantId $participant, int $limit, ?int $before = null): array
    {
        $sql = 'SELECT '.self::COLUMNS.', '.self::PARTICIPATION.', ('
            .'SELECT COUNT(*) FROM '.ConversationTables::MESSAGE.' m WHERE m.conversation_id = c.id AND m.seq > p.read_up_to AND (m.author IS NULL OR m.author <> p.participant)'
            .') AS unread FROM '.ConversationTables::PARTICIPANT.' p JOIN '.ConversationTables::CONVERSATION.' c ON c.id = p.conversation_id'
            .' WHERE p.participant = ? AND p.left_at IS NULL';
        $parameters = [$participant->value];
        $types = [ParameterType::STRING];

        if (null !== $before) {
            $sql .= ' AND c.last_sequence < ?';
            $parameters[] = $before;
            $types[] = ParameterType::INTEGER;
        }

        $rows = $this->connection->fetchAllAssociative($this->connection->getDatabasePlatform()->modifyLimitQuery($sql.' ORDER BY c.last_sequence DESC', $limit), $parameters, $types);

        return array_map(fn (array $row): InboxEntry => new InboxEntry($this->conversation($row), $this->participationOf($row), self::integer($row['unread'])), $rows);
    }

    #[Override]
    public function unreadConversations(ParticipantId $participant): int
    {
        return self::integer($this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.ConversationTables::PARTICIPANT.' p JOIN '.ConversationTables::CONVERSATION.' c ON c.id = p.conversation_id'
            .' WHERE p.participant = ? AND p.left_at IS NULL AND c.last_sequence > p.read_up_to',
            [$participant->value],
        ));
    }

    #[Override]
    public function conversationsOf(ParticipantId $participant): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.', '.self::PARTICIPATION.' FROM '.ConversationTables::PARTICIPANT.' p JOIN '.ConversationTables::CONVERSATION.' c ON c.id = p.conversation_id'
            .' WHERE p.participant = ? ORDER BY c.started_at, c.id',
            [$participant->value],
        );

        return array_map(fn (array $row): InboxEntry => new InboxEntry($this->conversation($row), $this->participationOf($row), 0), $rows);
    }

    #[Override]
    public function messagesBy(ParticipantId $participant): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT seq, id, conversation_id, author, body, sent_at FROM '.ConversationTables::MESSAGE.' WHERE author = ? ORDER BY seq', [$participant->value]);

        return array_map($this->message(...), $rows);
    }

    #[Override]
    public function forget(ParticipantId $participant): array
    {
        return $this->connection->transactional(static function (Connection $connection) use ($participant): array {
            $conversations = array_map(self::text(...), $connection->fetchFirstColumn('SELECT conversation_id FROM '.ConversationTables::PARTICIPANT.' WHERE participant = ?', [$participant->value]));
            $messages = $connection->executeStatement('UPDATE '.ConversationTables::MESSAGE.' SET author = NULL, body = NULL, body_key = NULL WHERE author = ?', [$participant->value]);
            $participations = $connection->executeStatement('DELETE FROM '.ConversationTables::PARTICIPANT.' WHERE participant = ?', [$participant->value]);
            $removed = 0;

            if ([] !== $conversations) {
                $connection->executeStatement('UPDATE '.ConversationTables::CONVERSATION.' SET direct_key = NULL WHERE id IN (?)', [$conversations], [ArrayParameterType::STRING]);
                $empty = array_map(self::text(...), $connection->fetchFirstColumn(
                    'SELECT c.id FROM '.ConversationTables::CONVERSATION.' c WHERE c.id IN (?) AND NOT EXISTS (SELECT 1 FROM '.ConversationTables::PARTICIPANT.' p WHERE p.conversation_id = c.id)',
                    [$conversations],
                    [ArrayParameterType::STRING],
                ));

                if ([] !== $empty) {
                    $connection->executeStatement('DELETE FROM '.ConversationTables::MESSAGE.' WHERE conversation_id IN (?)', [$empty], [ArrayParameterType::STRING]);
                    $removed = $connection->executeStatement('DELETE FROM '.ConversationTables::CONVERSATION.' WHERE id IN (?)', [$empty], [ArrayParameterType::STRING]);
                }
            }

            return ['messages' => self::integer($messages), 'participations' => self::integer($participations), 'conversations' => self::integer($removed)];
        });
    }

    /**
     * The context a subject is sealed in: the row of its conversation.
     */
    public static function subjectContext(ConversationId $id): string
    {
        return 'phil_conversation.subject.'.$id->value;
    }

    /**
     * The context a body is sealed in: the row of its message.
     */
    public static function messageContext(MessageId $id): string
    {
        return 'phil_conversation.message.'.$id->value;
    }

    /**
     * The key of the direct conversation of two participants, the same whichever comes first.
     */
    public static function directKey(ParticipantId $one, ParticipantId $other): string
    {
        $pair = [$one->value, $other->value];
        sort($pair, \SORT_STRING);

        return hash('sha256', implode("\0", $pair));
    }

    /**
     * Gives a direct conversation its key once its two participants are in. A key another conversation holds already
     * removes this one, which has no message yet, and throws.
     *
     * @throws DirectConversationExists when the two participants already share another direct conversation
     */
    private function direct(ConversationId $id): void
    {
        if (Kind::Direct->value !== $this->connection->fetchOne('SELECT kind FROM '.ConversationTables::CONVERSATION.' WHERE id = ? AND direct_key IS NULL', [$id->value])) {
            return;
        }

        $participants = array_map(self::text(...), $this->connection->fetchFirstColumn('SELECT participant FROM '.ConversationTables::PARTICIPANT.' WHERE conversation_id = ?', [$id->value]));

        if (2 !== \count($participants)) {
            return;
        }

        $key = self::directKey(ParticipantId::fromString($participants[0]), ParticipantId::fromString($participants[1]));

        try {
            // A transaction of its own, or a savepoint in the caller's: the refused update leaves the caller's open.
            $this->connection->transactional(static function (Connection $connection) use ($key, $id): void {
                $connection->update(ConversationTables::CONVERSATION, ['direct_key' => $key], ['id' => $id->value]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->connection->transactional(static function (Connection $connection) use ($id): void {
                $connection->delete(ConversationTables::PARTICIPANT, ['conversation_id' => $id->value]);
                $connection->delete(ConversationTables::CONVERSATION, ['id' => $id->value]);
            });

            throw DirectConversationExists::create();
        }
    }

    private function exists(ConversationId $id): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM '.ConversationTables::CONVERSATION.' WHERE id = ?', [$id->value]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function conversation(array $row): Conversation
    {
        $id = ConversationId::fromString(self::text($row['id']));
        $subject = null === $row['subject'] ? null : $this->sealer->open(self::text($row['subject']), self::subjectContext($id));

        return new Conversation(
            $id,
            Kind::from(self::text($row['kind'])),
            $subject,
            $this->instant($row['started_at']),
            $this->instant($row['last_message_at']),
            self::integer($row['last_sequence']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function participationOf(array $row): Participation
    {
        return new Participation(
            ConversationId::fromString(self::text($row['conversation_id'])),
            ParticipantId::fromString(self::text($row['participant'])),
            $this->instant($row['joined_at']),
            self::integer($row['read_up_to']),
            (bool) $this->connection->convertToPHPValue($row['muted'], Types::BOOLEAN),
            null === $row['left_at'] ? null : $this->instant($row['left_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function message(array $row): Message
    {
        $id = MessageId::fromString(self::text($row['id']));

        return new Message(
            $id,
            ConversationId::fromString(self::text($row['conversation_id'])),
            null === $row['author'] ? null : ParticipantId::fromString(self::text($row['author'])),
            null === $row['body'] ? null : $this->sealer->open(self::text($row['body']), self::messageContext($id)),
            $this->instant($row['sent_at']),
            self::integer($row['seq']),
        );
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $instant = $this->connection->convertToPHPValue($value, Types::DATETIME_UTC_IMMUTABLE);
        \assert($instant instanceof DateTimeImmutable);

        return $instant;
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
