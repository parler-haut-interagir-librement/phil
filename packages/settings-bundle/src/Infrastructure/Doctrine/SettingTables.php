<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\SettingsBundle\Domain\Setting\Keys;
use Phil\SettingsBundle\Domain\ValueObject\Subject;

/**
 * The bundle's tables: the values, one row per subject and key, encoded as JSON, keyed by the scope, the subject and
 * the key, so that a subject's values are read by the start of the primary key; and the versions of the migrations
 * applied, with when.
 *
 * **Keys compare byte by byte.** The scope, the subject, the key and the version of a migration identify a row: given
 * the platform, MySQL and MariaDB store them in `utf8mb4` with the `utf8mb4_bin` collation, so that the values of the
 * user `Ann` are never read for `ann`, as a server's default collation (`utf8mb4_0900_ai_ci`) would read them.
 * PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the default.
 */
final class SettingTables
{
    public const string SETTINGS = 'phil_settings';

    public const string MIGRATIONS = 'phil_settings_migrations';

    /** The longest version of a migration, the length of its column. */
    public const int VERSION_LENGTH = 190;

    /**
     * Adds the tables the schema lacks. Without a platform, the columns keep the connection's default collation: the
     * schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $binary = $platform instanceof AbstractMySQLPlatform;
        $missing = array_filter(
            [self::SETTINGS => self::settings(...), self::MIGRATIONS => self::migrations(...)],
            static fn (string $name): bool => !$schema->hasTable($name),
            \ARRAY_FILTER_USE_KEY,
        );

        if ([] === $missing) {
            return $schema;
        }

        $editor = $schema->edit();

        foreach ($missing as $table) {
            $editor->addTable($table($binary));
        }

        return $editor->create();
    }

    private static function migrations(bool $binary): Table
    {
        return Table::editor()
            ->setUnquotedName(self::MIGRATIONS)
            ->setColumns(
                self::string('version', self::VERSION_LENGTH, $binary),
                Column::editor()->setUnquotedName('applied_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version')->create())
            ->create();
    }

    private static function settings(bool $binary): Table
    {
        return Table::editor()
            ->setUnquotedName(self::SETTINGS)
            ->setColumns(
                self::string('scope', 8, $binary),
                self::string('subject', Subject::ID_LENGTH, $binary),
                self::string('setting_key', Keys::SETTING_LENGTH, $binary),
                Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scope', 'subject', 'setting_key')->create())
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $binary): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }
}
