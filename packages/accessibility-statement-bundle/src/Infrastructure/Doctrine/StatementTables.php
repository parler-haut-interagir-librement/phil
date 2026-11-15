<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of the bundle, added to the schema Doctrine generates so that the application's migrations create it:
 * `phil_accessibility_statement`, one row per version, its number as the key, the statement as a JSON document.
 */
final class StatementTables
{
    public const string STATEMENTS = 'phil_accessibility_statement';

    private function __construct()
    {
    }

    /**
     * The schema with the table added, left as it is when it has it already.
     */
    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::STATEMENTS)) {
            return $schema;
        }

        return $schema->edit()
            ->addTable(Table::editor()
                ->setUnquotedName(self::STATEMENTS)
                ->addColumn(Column::editor()->setUnquotedName('version')->setTypeName(Types::INTEGER)->create())
                ->addColumn(Column::editor()->setUnquotedName('published_at')->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->create())
                ->addColumn(Column::editor()->setUnquotedName('document')->setTypeName(Types::TEXT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version')->create())
                ->create())
            ->create();
    }
}
