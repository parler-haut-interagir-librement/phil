<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of documents: one row per index and identifier, its words in lower case, and its filters.
 */
final class SearchTable
{
    public const string NAME = 'phil_search_document';

    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->setColumns(
                Column::editor()->setUnquotedName('index_name')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('document_id')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('content')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('filters')->setTypeName(Types::JSON)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_name', 'document_id')->create())
            ->create())->create();
    }
}
