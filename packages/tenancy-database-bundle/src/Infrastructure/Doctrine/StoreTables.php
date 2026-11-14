<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The two tables of the durable stores, in the shared database: the placement of each tenant, and the suspended
 * databases and tenants.
 *
 * Each row is found by the SHA-256 of what it is about, in hexadecimal: a tenant reference is compared byte for byte
 * whatever the collation of the server, which on MySQL would read `Rose` and `rose` as one key. The reference and the
 * database name are kept beside it, for the operator who reads the table.
 *
 * A migration creates them with {@see self::configure()}, or `doctrine:migrations:diff` finds them through
 * {@see StoreSchemaListener}.
 */
final class StoreTables
{
    public const string PLACEMENTS = 'phil_tenancy_placement';

    public const string SUSPENSIONS = 'phil_tenancy_suspension';

    private function __construct()
    {
    }

    /**
     * Adds the tables to a schema, each unless it is there already.
     */
    public static function configure(Schema $schema, bool $placements = true, bool $suspensions = true): Schema
    {
        if ($placements && !$schema->hasTable(self::PLACEMENTS)) {
            $schema = $schema->edit()->addTable(Table::editor()
                ->setUnquotedName(self::PLACEMENTS)
                ->setColumns(
                    self::string('id', 64, fixed: true),
                    self::string('tenant', 255),
                    self::string('database_name', 63, nullable: true),
                    self::now('placed_at'),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->create())->create();
        }

        if ($suspensions && !$schema->hasTable(self::SUSPENSIONS)) {
            $schema = $schema->edit()->addTable(Table::editor()
                ->setUnquotedName(self::SUSPENSIONS)
                ->setColumns(
                    self::string('id', 64, fixed: true),
                    self::string('kind', 8),
                    self::string('subject', 255, nullable: true),
                    self::now('suspended_at'),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->create())->create();
        }

        return $schema;
    }

    /**
     * Inserts a row, or, when its `id` is taken, replaces the `$update` columns of the row there with the new values and
     * sets `$touched` to the current time; with neither, leaves the row there as it is.
     *
     * @param non-empty-array<string, ?string> $row     the values of the row, by column
     * @param list<string>                     $update  the columns of `$row` that replace those of the row there
     * @param ?string                          $touched a column set to the current time on the row there
     */
    public static function upsert(Connection $connection, string $table, array $row, array $update = [], ?string $touched = null): void
    {
        $mysql = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
        $parameters = array_values($row);
        $sets = [];

        foreach ($update as $column) {
            $sets[] = $mysql ? $column.' = ?' : $column.' = EXCLUDED.'.$column;

            if ($mysql) {
                $parameters[] = $row[$column];
            }
        }

        if (null !== $touched) {
            $sets[] = $touched.' = CURRENT_TIMESTAMP';
        }

        $insert = \sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', array_keys($row)), implode(', ', array_fill(0, \count($row), '?')));

        $connection->executeStatement(match (true) {
            // MySQL and MariaDB have no `DO NOTHING`: a key set to itself changes nothing.
            $mysql => $insert.' ON DUPLICATE KEY UPDATE '.([] === $sets ? 'id = id' : implode(', ', $sets)),
            [] === $sets => $insert.' ON CONFLICT (id) DO NOTHING',
            default => $insert.' ON CONFLICT (id) DO UPDATE SET '.implode(', ', $sets),
        }, $parameters);
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $fixed = false, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setFixed($fixed)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function now(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_IMMUTABLE)->setDefaultValue('CURRENT_TIMESTAMP')->create();
    }
}
