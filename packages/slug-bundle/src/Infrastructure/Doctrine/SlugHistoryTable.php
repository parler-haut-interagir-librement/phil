<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\Slug\Slug;
use Phil\SlugBundle\Domain\SlugHistoryKeys;

/**
 * The table of the slug history, `phil_slug_history`, added to the schema Doctrine generates so that the
 * application's migrations create it: one row per former slug of a scope, with the subject that had it, the subject's
 * current slug and when the change was recorded. The primary key is the scope and the former slug, which is what a
 * lookup reads; a second index on the scope and the subject serves the updates of a change and `forget()`.
 */
final class SlugHistoryTable
{
    public const string NAME = 'phil_slug_history';

    private function __construct()
    {
    }

    /**
     * The schema with the table added, left as it is when it already has it.
     */
    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        $editor = $schema->edit();
        $editor->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->addColumn(self::string('scope', SlugHistoryKeys::SCOPE_MAX_LENGTH))
            ->addColumn(self::string('former_slug', Slug::MAX_LENGTH))
            ->addColumn(self::string('subject', SlugHistoryKeys::SUBJECT_MAX_LENGTH))
            ->addColumn(self::string('current_slug', Slug::MAX_LENGTH))
            ->addColumn(Column::editor()->setUnquotedName('recorded_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(true)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scope', 'former_slug')->create())
            ->addIndex(Index::editor()->setUnquotedName(self::NAME.'_subject')->setUnquotedColumnNames('scope', 'subject')->create())
            ->create());

        return $editor->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(true)->create();
    }
}
