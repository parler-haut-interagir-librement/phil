<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of documents: one row per index and identifier, its words in lower case, and its filters.
 *
 * The index and the identifier compare byte by byte: given the platform, MySQL and MariaDB store them in `utf8mb4` with
 * the `utf8mb4_bin` collation, so that the documents `Post-A` and `post-a` are two rows and indexing one never replaces
 * the other. PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the
 * default. The words keep the connection's collation, which the search reads them under.
 */
final class SearchTable
{
    public const string NAME = 'phil_search_document';

    /**
     * Adds the table when the schema lacks it. Without a platform, the columns keep the connection's default collation:
     * the schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->setColumns(
                self::key('index_name', 64, $platform),
                self::key('document_id', 255, $platform),
                Column::editor()->setUnquotedName('content')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('filters')->setTypeName(Types::JSON)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('index_name', 'document_id')->create())
            ->create())->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function key(string $name, int $length, ?AbstractPlatform $platform): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }
}
