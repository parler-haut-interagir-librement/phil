<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The tables of the error tracking: `phil_error_group`, one row per fingerprint with its status, count, instants and
 * releases, and `phil_error_event`, the scrubbed events of each group as JSON, numbered in the order they came.
 */
final class ErrorTables
{
    public const string GROUP = 'phil_error_group';

    public const string EVENT = 'phil_error_event';

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, each left as it is when the schema already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::GROUP)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::GROUP)
                ->addColumn(self::key('fingerprint', Types::STRING, 64)->edit()->setFixed(true)->create())
                ->addColumn(self::column('title', Types::TEXT, true))
                ->addColumn(self::column('culprit', Types::TEXT, false))
                ->addColumn(self::column('status', Types::STRING, true, 16))
                ->addColumn(self::column('times_seen', Types::BIGINT, true))
                ->addColumn(self::column('first_seen_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('last_seen_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('last_release', Types::STRING, false, 128))
                ->addColumn(self::column('resolved_release', Types::STRING, false, 128))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('fingerprint')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_error_group_status')->setUnquotedColumnNames('status', 'last_seen_at')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_error_group_seen')->setUnquotedColumnNames('last_seen_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::EVENT)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::EVENT)
                ->addColumn(self::key('id', Types::BIGINT)->edit()->setAutoincrement(true)->create())
                ->addColumn(self::column('event_id', Types::STRING, true, 32)->edit()->setFixed(true)->create())
                ->addColumn(self::column('fingerprint', Types::STRING, true, 64)->edit()->setFixed(true)->create())
                ->addColumn(self::column('occurred_at', Types::DATETIME_UTC_IMMUTABLE, true))
                ->addColumn(self::column('payload', Types::TEXT, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_error_event_group')->setUnquotedColumnNames('fingerprint', 'id')->create())
                ->addIndex(Index::editor()->setUnquotedName('idx_phil_error_event_time')->setUnquotedColumnNames('occurred_at')->create())
                ->create());
        }

        return $editor->create();
    }

    /**
     * A column of a primary key, which every platform makes not null.
     *
     * @param non-empty-string $name
     */
    private static function key(string $name, string $type, ?int $length = null): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $required, ?int $length = null): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required)->create();
    }
}
