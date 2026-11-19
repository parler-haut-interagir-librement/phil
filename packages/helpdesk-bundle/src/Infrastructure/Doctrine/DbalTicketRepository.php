<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Repository\TicketRepository;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

/**
 * The requests kept through DBAL, without the ORM, so that a long queue is read by page; moments in UTC.
 */
final readonly class DbalTicketRepository implements TicketRepository
{
    private const string COLUMNS = 'conversation_id, queue, status, requester, assignee, opened_at, updated_at, closed_at';

    private const array TYPES = ['opened_at' => Types::DATETIME_UTC_IMMUTABLE, 'updated_at' => Types::DATETIME_UTC_IMMUTABLE, 'closed_at' => Types::DATETIME_UTC_IMMUTABLE];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function find(ConversationId $conversation): ?Ticket
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.HelpdeskTables::TICKET.' WHERE conversation_id = ?', [$conversation->value]);

        return false === $row ? null : $this->ticket($row);
    }

    #[Override]
    public function save(Ticket $ticket): void
    {
        $values = [
            'queue' => $ticket->queue()->value,
            'status' => $ticket->status()->value,
            'requester' => $ticket->requester()?->value,
            'assignee' => $ticket->assignee()?->value,
            'updated_at' => $ticket->updatedAt(),
            'closed_at' => $ticket->closedAt(),
        ];
        $key = ['conversation_id' => $ticket->conversation->value];

        if (false === $this->connection->fetchOne('SELECT 1 FROM '.HelpdeskTables::TICKET.' WHERE conversation_id = ?', [$ticket->conversation->value])) {
            $this->connection->insert(HelpdeskTables::TICKET, $key + $values + ['opened_at' => $ticket->openedAt], self::TYPES);
        } else {
            $this->connection->update(HelpdeskTables::TICKET, $values, $key, self::TYPES);
        }
    }

    #[Override]
    public function inQueues(array $queues, ?Status $status, ?ParticipantId $assignee, int $limit, int $offset = 0): array
    {
        $sql = 'SELECT '.self::COLUMNS.' FROM '.HelpdeskTables::TICKET.' WHERE queue IN (?)';
        $parameters = [array_map(static fn (QueueName $queue): string => $queue->value, $queues)];
        $types = [ArrayParameterType::STRING];

        if ($status instanceof Status) {
            $sql .= ' AND status = ?';
            $parameters[] = $status->value;
            $types[] = ParameterType::STRING;
        }

        if ($assignee instanceof ParticipantId) {
            $sql .= ' AND assignee = ?';
            $parameters[] = $assignee->value;
            $types[] = ParameterType::STRING;
        }

        return $this->page($sql, $parameters, $types, $limit, $offset);
    }

    #[Override]
    public function ofRequester(ParticipantId $requester, int $limit, int $offset = 0): array
    {
        return $this->page('SELECT '.self::COLUMNS.' FROM '.HelpdeskTables::TICKET.' WHERE requester = ?', [$requester->value], [ParameterType::STRING], $limit, $offset);
    }

    #[Override]
    public function concerning(ParticipantId $participant): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM '.HelpdeskTables::TICKET.' WHERE requester = ? OR assignee = ? ORDER BY opened_at, conversation_id',
            [$participant->value, $participant->value],
        );

        return array_map($this->ticket(...), $rows);
    }

    #[Override]
    public function forget(ParticipantId $participant): int
    {
        return $this->connection->transactional(static function (Connection $connection) use ($participant): int {
            $requested = $connection->executeStatement('UPDATE '.HelpdeskTables::TICKET.' SET requester = NULL WHERE requester = ?', [$participant->value]);
            $assigned = $connection->executeStatement('UPDATE '.HelpdeskTables::TICKET.' SET assignee = NULL WHERE assignee = ?', [$participant->value]);

            return (int) $requested + (int) $assigned;
        });
    }

    /**
     * @param list<mixed>                                   $parameters
     * @param list<ParameterType|ArrayParameterType|string> $types
     *
     * @return list<Ticket>
     */
    private function page(string $sql, array $parameters, array $types, int $limit, int $offset): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql.' ORDER BY updated_at DESC, conversation_id DESC', $limit, $offset);

        return array_map($this->ticket(...), $this->connection->fetchAllAssociative($sql, $parameters, $types));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function ticket(array $row): Ticket
    {
        return new Ticket(
            ConversationId::fromString($this->text($row['conversation_id'])),
            QueueName::fromString($this->text($row['queue'])),
            null === $row['requester'] ? null : ParticipantId::fromString($this->text($row['requester'])),
            $this->instant($row['opened_at']),
            Status::from($this->text($row['status'])),
            null === $row['assignee'] ? null : ParticipantId::fromString($this->text($row['assignee'])),
            $this->instant($row['updated_at']),
            null === $row['closed_at'] ? null : $this->instant($row['closed_at']),
        );
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $instant = $this->connection->convertToPHPValue($value, Types::DATETIME_UTC_IMMUTABLE);
        \assert($instant instanceof DateTimeImmutable);

        return $instant;
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
