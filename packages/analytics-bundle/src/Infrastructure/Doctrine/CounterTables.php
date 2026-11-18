<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * The two tables of the counters: one number per site, UTC day, metric, dimension and value, then one per month once the
 * days are rolled up. Nothing else: no instant finer than the day, no address, no user agent. Days and months are kept
 * as `YYYY-MM-DD` and `YYYY-MM` text, which sorts as the calendar does on every platform.
 *
 * **Keys compare byte by byte.** The site, the metric, the dimension and its value are the key of a counter, and the
 * metric decides who may read it: given the platform, MySQL and MariaDB store them in `utf8mb4` with the `utf8mb4_bin`
 * collation, where a server's default (`utf8mb4_0900_ai_ci`) would read `OUTBOUND_LINK` as `outbound_link` and `Acme`
 * as `acme`. PostgreSQL's default collations are deterministic and SQLite compares bytes: both already tell those
 * apart, and their columns keep the default.
 */
final class CounterTables
{
    public const string DAILY = 'phil_analytics_counter';

    public const string MONTHLY = 'phil_analytics_monthly_counter';

    /**
     * Adds both tables to the schema, each when it is not there yet. Without a platform, the columns keep the
     * connection's default collation: the schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $binary = $platform instanceof AbstractMySQLPlatform;

        foreach ([self::DAILY => ['day', 10], self::MONTHLY => ['month', 7]] as $name => [$period, $length]) {
            if ($schema->hasTable($name)) {
                continue;
            }

            $schema = $schema->edit()->addTable(Table::editor()
                ->setUnquotedName($name)
                ->setColumns(
                    self::string('site', SiteId::MAX_LENGTH, $binary),
                    self::string($period, $length),
                    self::string('metric', 64, $binary),
                    self::string('dimension', 64, $binary),
                    self::string('dimension_value', CounterKey::MAX_VALUE_LENGTH, $binary),
                    Column::editor()->setUnquotedName('n')->setTypeName(Types::BIGINT)->create(),
                )
                ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('site', $period, 'metric', 'dimension', 'dimension_value')->create())
                ->create())->create();
        }

        return $schema;
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $binary = false): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }
}
