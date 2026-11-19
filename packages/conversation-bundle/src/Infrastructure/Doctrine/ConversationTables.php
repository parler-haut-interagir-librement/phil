<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * The tables of the conversations: `phil_conversation`, its sealed subject and the sequence of its last message;
 * `phil_conversation_participant`, each participant's reading, muting and departure; `phil_conversation_message`,
 * the sealed bodies numbered in the order they came; `phil_conversation_reception` and `phil_conversation_block`, the
 * reception settings. Each sealed text keeps the name of its master key beside it, so that a rotation finds the texts
 * of the previous keys. Moments are kept in UTC.
 */
final class ConversationTables
{
    public const string CONVERSATION = 'phil_conversation';

    public const string PARTICIPANT = 'phil_conversation_participant';

    public const string MESSAGE = 'phil_conversation_message';

    public const string RECEPTION = 'phil_conversation_reception';

    public const string BLOCK = 'phil_conversation_block';

    private const int KEY_LENGTH = 190;

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, each left as it is when the schema already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::CONVERSATION)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::CONVERSATION)
                ->addColumn(self::uuid('id'))
                ->addColumn(self::column('kind', Types::STRING, true, 8))
                ->addColumn(self::column('direct_key', Types::STRING, false, 64)->edit()->setFixed(true)->create())
                ->addColumn(self::column('subject', Types::TEXT, false))
                ->addColumn(self::column('subject_key', Types::STRING, false, self::KEY_LENGTH))
                ->addColumn(self::column('started_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('last_message_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('last_sequence', Types::BIGINT, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName('uniq_phil_conversation_direct')->setUnquotedColumnNames('direct_key')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_conversation_subject_key')->setUnquotedColumnNames('subject_key')->create())
                ->create());
        }

        if (!$schema->hasTable(self::PARTICIPANT)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::PARTICIPANT)
                ->addColumn(self::uuid('conversation_id'))
                ->addColumn(self::column('participant', Types::STRING, true, ParticipantId::MAX_LENGTH))
                ->addColumn(self::column('joined_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('read_up_to', Types::BIGINT, true))
                ->addColumn(self::column('muted', Types::BOOLEAN, true))
                ->addColumn(self::column('left_at', Types::DATETIME_UTC_IMMUTABLE, false))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('conversation_id', 'participant')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_conversation_participant')->setUnquotedColumnNames('participant', 'left_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::MESSAGE)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::MESSAGE)
                ->addColumn(Column::editor()->setUnquotedName('seq')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create())
                ->addColumn(self::uuid('id'))
                ->addColumn(self::uuid('conversation_id'))
                ->addColumn(self::column('author', Types::STRING, false, ParticipantId::MAX_LENGTH))
                ->addColumn(self::column('body', Types::TEXT, false))
                ->addColumn(self::column('body_key', Types::STRING, false, self::KEY_LENGTH))
                ->addColumn(self::column('sent_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('seq')->create())
                ->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName('uniq_phil_conversation_message')->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_conversation_message_thread')->setUnquotedColumnNames('conversation_id', 'seq')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_conversation_message_author')->setUnquotedColumnNames('author')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_conversation_message_key')->setUnquotedColumnNames('body_key')->create())
                ->create());
        }

        if (!$schema->hasTable(self::RECEPTION)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::RECEPTION)
                ->addColumn(self::column('participant', Types::STRING, true, ParticipantId::MAX_LENGTH))
                ->addColumn(self::column('who_can_write', Types::STRING, true, 16))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('participant')->create())
                ->create());
        }

        if (!$schema->hasTable(self::BLOCK)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::BLOCK)
                ->addColumn(self::column('participant', Types::STRING, true, ParticipantId::MAX_LENGTH))
                ->addColumn(self::column('blocked', Types::STRING, true, ParticipantId::MAX_LENGTH))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('participant', 'blocked')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_conversation_block_blocked')->setUnquotedColumnNames('blocked')->create())
                ->create());
        }

        return $editor->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function uuid(string $name): Column
    {
        return self::column($name, Types::STRING, true, 36)->edit()->setFixed(true)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $required, ?int $length = null): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required)->create();
    }
}
