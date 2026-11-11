<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * The two tables of the database store, added to the schema Doctrine generates so that the application's
 * migrations create them:
 *
 * - `phil_feature_flags_definition`: the flags created from the admin, each definition in the JSON shape of
 *   `FlagDefinitionFormat`, with the revision each edit increments;
 * - `phil_feature_flags_state`: the active state of any flag, from any provider, that has been toggled.
 */
final class FlagTables
{
    public const string DEFINITIONS = 'phil_feature_flags_definition';

    public const string STATES = 'phil_feature_flags_state';

    private function __construct()
    {
    }

    /**
     * The schema with the two tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::DEFINITIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::DEFINITIONS)
                ->addColumn(self::name())
                ->addColumn(Column::editor()->setUnquotedName('definition')->setTypeName(Types::JSON)->setNotNull(true)->create())
                ->addColumn(Column::editor()->setUnquotedName('revision')->setTypeName(Types::INTEGER)->setNotNull(true)->create())
                ->addColumn(self::updatedAt())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('name')->create())
                ->create());
        }

        if (!$schema->hasTable(self::STATES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::STATES)
                ->addColumn(self::name())
                ->addColumn(Column::editor()->setUnquotedName('active')->setTypeName(Types::BOOLEAN)->setNotNull(true)->create())
                ->addColumn(self::updatedAt())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('name')->create())
                ->create());
        }

        return $editor->create();
    }

    private static function name(): Column
    {
        return Column::editor()->setUnquotedName('name')->setTypeName(Types::STRING)->setLength(FlagName::MAX_LENGTH)->create();
    }

    private static function updatedAt(): Column
    {
        return Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(true)->create();
    }
}
