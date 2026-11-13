<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Dbal;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;

/**
 * The table of the activations, `phil_incident_activation`: one row each, the restrictions as their names joined by
 * commas, the moments in UTC (`datetime_utc_immutable`), so that two servers in different time zones read the same
 * end. One index serves the question asked on every check — the rows not lifted whose end is ahead —, another the
 * journal.
 */
final class IncidentTables
{
    public const string ACTIVATIONS = 'phil_incident_activation';

    private function __construct()
    {
    }

    /**
     * The schema with the table added, left as it is when it already has it: the one the ORM generates, through
     * `IncidentSchemaListener`, or one a migration builds.
     */
    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::ACTIVATIONS)) {
            return $schema;
        }

        return $schema->edit()->addTable(self::table())->create();
    }

    public static function table(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::ACTIVATIONS)
            ->addColumn(self::column('id', Types::STRING, Activation::ID_LENGTH, true)->edit()->setFixed(true)->create())
            ->addColumn(self::column('preset', Types::STRING, 64, true))
            ->addColumn(self::column('restrictions', Types::TEXT, null, true))
            ->addColumn(self::column('started_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
            ->addColumn(self::column('ends_at', Types::DATETIME_UTC_IMMUTABLE, null, true))
            ->addColumn(self::column('started_by', Types::STRING, Actor::MAX_LENGTH, false))
            ->addColumn(self::column('reason', Types::TEXT, null, false))
            ->addColumn(self::column('lifted_at', Types::DATETIME_UTC_IMMUTABLE, null, false))
            ->addColumn(self::column('lifted_by', Types::STRING, Actor::MAX_LENGTH, false))
            ->addColumn(self::column('lift_reason', Types::STRING, 16, false))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_phil_incident_in_force')->setUnquotedColumnNames('lifted_at', 'ends_at')->create())
            ->addIndex(Index::editor()->setUnquotedName('idx_phil_incident_started')->setUnquotedColumnNames('started_at')->create())
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, ?int $length, bool $required): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setLength($length)->setNotNull($required)->create();
    }
}
