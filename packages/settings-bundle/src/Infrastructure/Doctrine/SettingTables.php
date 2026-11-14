<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Doctrine;

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
 */
final class SettingTables
{
    public const string SETTINGS = 'phil_settings';

    public const string MIGRATIONS = 'phil_settings_migrations';

    /** The longest version of a migration, the length of its column. */
    public const int VERSION_LENGTH = 190;

    public static function configure(Schema $schema): Schema
    {
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
            $editor->addTable($table());
        }

        return $editor->create();
    }

    private static function migrations(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::MIGRATIONS)
            ->setColumns(
                self::string('version', self::VERSION_LENGTH),
                Column::editor()->setUnquotedName('applied_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version')->create())
            ->create();
    }

    private static function settings(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::SETTINGS)
            ->setColumns(
                self::string('scope', 8),
                self::string('subject', Subject::ID_LENGTH),
                self::string('setting_key', Keys::SETTING_LENGTH),
                Column::editor()->setUnquotedName('value')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scope', 'subject', 'setting_key')->create())
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->create();
    }
}
