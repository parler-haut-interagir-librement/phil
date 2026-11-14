<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Migration\StoredValue;
use Phil\SettingsBundle\Domain\ValueObject\Subject;

/**
 * The values in the bundle's table, one row each. A save removes the rows of every key it touches and inserts the new
 * values, in one transaction: of two saves of the same key at the same moment, the second fails on the primary key
 * rather than mixing them.
 *
 * The migrations read every value of a key, and write their changes with the record of their version in one
 * transaction: of two runs of the same migration at once, the second fails on the version's primary key.
 */
final readonly class DbalSettingRepository implements SettingRepository, SettingMigrationStore
{
    private const string TABLE = SettingTables::SETTINGS;

    private const string MIGRATIONS = SettingTables::MIGRATIONS;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function values(Subject $subject): array
    {
        $values = [];

        foreach ($this->connection->fetchAllNumeric(\sprintf('SELECT setting_key, value FROM %s WHERE scope = ? AND subject = ?', self::TABLE), [$subject->scope->value, $subject->id]) as [$key, $value]) {
            // Both columns are strings on every driver: the assertions only tell PHPStan so.
            \assert(\is_string($key));
            \assert(\is_string($value));
            $values[$key] = $value;
        }

        return $values;
    }

    #[Override]
    public function save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($subject, $values, $removed, $at): void {
            $connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE scope = ? AND subject = ? AND setting_key IN (?)', self::TABLE),
                [$subject->scope->value, $subject->id, [...$removed, ...array_keys($values)]],
                [Types::STRING, Types::STRING, ArrayParameterType::STRING],
            );

            foreach ($values as $key => $value) {
                $connection->insert(
                    self::TABLE,
                    ['scope' => $subject->scope->value, 'subject' => $subject->id, 'setting_key' => $key, 'value' => $value, 'updated_at' => $at],
                    ['updated_at' => Types::DATETIME_IMMUTABLE],
                );
            }
        });
    }

    #[Override]
    public function erase(Subject $subject): array
    {
        return $this->connection->transactional(static function (Connection $connection) use ($subject): array {
            $keys = [];

            foreach ($connection->fetchFirstColumn(\sprintf('SELECT setting_key FROM %s WHERE scope = ? AND subject = ? ORDER BY setting_key', self::TABLE), [$subject->scope->value, $subject->id]) as $key) {
                \assert(\is_string($key));
                $keys[] = $key;
            }

            $connection->delete(self::TABLE, ['scope' => $subject->scope->value, 'subject' => $subject->id]);

            return $keys;
        });
    }

    #[Override]
    public function appliedMigrations(): array
    {
        $versions = [];

        foreach ($this->connection->fetchFirstColumn(\sprintf('SELECT version FROM %s ORDER BY version', self::MIGRATIONS)) as $version) {
            \assert(\is_string($version));
            $versions[] = $version;
        }

        return $versions;
    }

    #[Override]
    public function valuesOf(string $key): array
    {
        $values = [];

        foreach ($this->connection->fetchAllNumeric(\sprintf('SELECT scope, subject, value FROM %s WHERE setting_key = ? ORDER BY scope, subject', self::TABLE), [$key]) as [$scope, $subject, $value]) {
            \assert(\is_string($scope) && \is_string($subject) && \is_string($value));
            $values[] = new StoredValue($scope, $subject, $key, $value);
        }

        return $values;
    }

    #[Override]
    public function applyMigration(string $version, array $changes, DateTimeImmutable $at): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($version, $changes, $at): void {
            foreach ($changes as $change) {
                $connection->delete(self::TABLE, ['scope' => $change->scope, 'subject' => $change->subject, 'setting_key' => $change->key]);

                if (null !== $change->value) {
                    $connection->insert(
                        self::TABLE,
                        ['scope' => $change->scope, 'subject' => $change->subject, 'setting_key' => $change->key, 'value' => $change->value, 'updated_at' => $at],
                        ['updated_at' => Types::DATETIME_IMMUTABLE],
                    );
                }
            }

            $connection->insert(self::MIGRATIONS, ['version' => $version, 'applied_at' => $at], ['applied_at' => Types::DATETIME_IMMUTABLE]);
        });
    }
}
