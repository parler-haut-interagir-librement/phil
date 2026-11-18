<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of usages: one row per source, field and target, indexed by target for "where is it used" and by source
 * for its refresh. The row's key is a SHA-256 of the five columns: a primary key over the columns themselves, 893
 * characters, would pass MySQL's 3072 bytes in utf8mb4, while each index here stays within it.
 *
 * Kinds, identifiers and fields compare byte by byte: given the platform, MySQL and MariaDB store them in `utf8mb4` with
 * the `utf8mb4_bin` collation, so that the usages of the source `Doc-A` are never read, nor forgotten, for `doc-a`.
 * PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the default.
 */
final class UsageTable
{
    public const string NAME = 'phil_reference_usage';

    /**
     * The key of a row: the SHA-256, in hexadecimal, of its five columns as a JSON list.
     */
    public static function keyOf(string $sourceKind, string $sourceId, string $field, string $targetKind, string $targetId): string
    {
        return hash('sha256', json_encode([$sourceKind, $sourceId, $field, $targetKind, $targetId], \JSON_THROW_ON_ERROR));
    }

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
                self::key('usage_key', 64, $platform)->setFixed(true)->create(),
                self::key('source_kind', 64, $platform)->create(),
                self::key('source_id', 255, $platform)->create(),
                self::key('field', 255, $platform)->create(),
                self::key('target_kind', 64, $platform)->create(),
                self::key('target_id', 255, $platform)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('usage_key')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::NAME.'_target')->setUnquotedColumnNames('target_kind', 'target_id')->create(),
                Index::editor()->setUnquotedName(self::NAME.'_source')->setUnquotedColumnNames('source_kind', 'source_id')->create(),
            )
            ->create())->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function key(string $name, int $length, ?AbstractPlatform $platform): ColumnEditor
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column;
    }
}
