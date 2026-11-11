<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of usages: one row per source, field and target, indexed by target for "where is it used" and by source
 * for its refresh. The row's key is a SHA-256 of the five columns: a primary key over the columns themselves, 893
 * characters, would pass MySQL's 3072 bytes in utf8mb4, while each index here stays within it.
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

    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->setColumns(
                Column::editor()->setUnquotedName('usage_key')->setTypeName(Types::STRING)->setLength(64)->setFixed(true)->create(),
                Column::editor()->setUnquotedName('source_kind')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('source_id')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('field')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('target_kind')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('target_id')->setTypeName(Types::STRING)->setLength(255)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('usage_key')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::NAME.'_target')->setUnquotedColumnNames('target_kind', 'target_id')->create(),
                Index::editor()->setUnquotedName(self::NAME.'_source')->setUnquotedColumnNames('source_kind', 'source_id')->create(),
            )
            ->create())->create();
    }
}
