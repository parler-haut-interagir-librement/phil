<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;

/**
 * The tuple table of version 0.1, without partitions, as an application upgrading from it has it, with one row.
 */
final class LegacyTupleTable
{
    public static function create(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS '.DbalTupleStore::TABLE);
        $platform = $connection->getDatabasePlatform();
        $collation = match (true) {
            $platform instanceof AbstractMySQLPlatform => 'utf8mb4_bin',
            $platform instanceof PostgreSQLPlatform => 'C',
            default => null,
        };
        $text = static function (string $name, int $length) use ($collation): Column {
            \assert('' !== $name);
            $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

            return (null === $collation ? $column : $column->setCollation($collation))->create();
        };

        $connection->createSchemaManager()->createTable(Table::editor()
            ->setUnquotedName(DbalTupleStore::TABLE)
            ->setColumns(
                $text('object_kind', 64),
                $text('object_id', 255),
                $text('relation', 64),
                $text('subject_kind', 64),
                $text('subject_id', 255),
                $text('subject_relation', 64),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('object_kind', 'object_id', 'relation', 'subject_kind', 'subject_id', 'subject_relation')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(DbalTupleStore::TABLE.'_subject')->setUnquotedColumnNames('subject_kind', 'subject_id', 'subject_relation', 'object_kind', 'relation')->create(),
                Index::editor()->setUnquotedName(DbalTupleStore::TABLE.'_expires_at')->setUnquotedColumnNames('expires_at')->create(),
            )
            ->create());

        $connection->executeStatement(
            \sprintf("INSERT INTO %s (object_kind, object_id, relation, subject_kind, subject_id, subject_relation, expires_at) VALUES ('doc', 'Ann-é', 'viewer', 'member', 'ann', '', NULL)", DbalTupleStore::TABLE),
        );
    }
}
