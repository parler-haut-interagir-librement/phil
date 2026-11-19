<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The table of the help desk, `phil_helpdesk_ticket`: one row per request, named by its conversation, with its queue,
 * status, requester, assignee and moments, in UTC.
 */
final class HelpdeskTables
{
    public const string TICKET = 'phil_helpdesk_ticket';

    private function __construct()
    {
    }

    /**
     * The schema with the table added, left as it is when the schema already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::TICKET)) {
            return $schema;
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::TICKET)
            ->addColumn(self::column('conversation_id', Types::STRING, true, 36)->edit()->setFixed(true)->create())
            ->addColumn(self::column('queue', Types::STRING, true, 64))
            ->addColumn(self::column('status', Types::STRING, true, 16))
            ->addColumn(self::column('requester', Types::STRING, false, ParticipantId::MAX_LENGTH))
            ->addColumn(self::column('assignee', Types::STRING, false, ParticipantId::MAX_LENGTH))
            ->addColumn(self::column('opened_at', Types::DATETIME_UTC_IMMUTABLE, true))
            ->addColumn(self::column('updated_at', Types::DATETIME_UTC_IMMUTABLE, true))
            ->addColumn(self::column('closed_at', Types::DATETIME_UTC_IMMUTABLE, false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('conversation_id')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_phil_helpdesk_ticket_queue')->setUnquotedColumnNames('queue', 'status', 'updated_at')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_phil_helpdesk_ticket_requester')->setUnquotedColumnNames('requester', 'updated_at')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_phil_helpdesk_ticket_assignee')->setUnquotedColumnNames('assignee')->create())
            ->create())->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $required, ?int $length = null): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required)->create();
    }
}
