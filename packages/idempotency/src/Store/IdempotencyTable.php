<?php

declare(strict_types=1);

namespace Phil\Idempotency\Store;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;

/**
 * The table of `DbalIdempotencyStore`: one row per scope and key, its fingerprint, its state, its response once
 * there is one, and when it expires, indexed for the purge.
 *
 * Scopes and keys compare byte for byte. On MySQL and MariaDB, whose default collations fold case and accents, the two
 * columns take `utf8mb4_bin` when the platform is given: without it, `Acme` and `acme` would name the same row.
 */
final class IdempotencyTable
{
    public const string NAME = 'phil_idempotency';

    /**
     * The schema with the table added, for a migration or a schema listener; a schema that has it is returned as is.
     * Give the platform of the connection, so that MySQL and MariaDB compare scopes and keys byte for byte.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        if ($schema->hasTable(self::NAME)) {
            return $schema;
        }

        $collation = $platform instanceof AbstractMySQLPlatform ? 'utf8mb4_bin' : null;
        $charset = null === $collation ? null : 'utf8mb4';

        return $schema->edit()->addTable(Table::editor()
            ->setUnquotedName(self::NAME)
            ->setColumns(
                Column::editor()->setUnquotedName('scope')->setTypeName(Types::STRING)->setLength(IdempotencyScope::MAX_LENGTH)->setCharset($charset)->setCollation($collation)->create(),
                Column::editor()->setUnquotedName('idempotency_key')->setTypeName(Types::STRING)->setLength(IdempotencyKey::MAX_LENGTH)->setCharset($charset)->setCollation($collation)->create(),
                Column::editor()->setUnquotedName('fingerprint')->setTypeName(Types::STRING)->setLength(64)->setFixed(true)->create(),
                Column::editor()->setUnquotedName('state')->setTypeName(Types::STRING)->setLength(16)->create(),
                Column::editor()->setUnquotedName('status')->setTypeName(Types::SMALLINT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('headers')->setTypeName(Types::JSON)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('body')->setTypeName(Types::BLOB)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scope', 'idempotency_key')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::NAME.'_expires_at')->setUnquotedColumnNames('expires_at')->create())
            ->create())->create();
    }
}
