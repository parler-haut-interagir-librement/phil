<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Doctrine;

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
 */
final class CounterTables
{
    public const string DAILY = 'phil_analytics_counter';

    public const string MONTHLY = 'phil_analytics_monthly_counter';

    public static function configure(Schema $schema): Schema
    {
        foreach ([self::DAILY => ['day', 10], self::MONTHLY => ['month', 7]] as $name => [$period, $length]) {
            if ($schema->hasTable($name)) {
                continue;
            }

            $schema = $schema->edit()->addTable(Table::editor()
                ->setUnquotedName($name)
                ->setColumns(
                    self::string('site', SiteId::MAX_LENGTH),
                    self::string($period, $length),
                    self::string('metric', 64),
                    self::string('dimension', 64),
                    self::string('dimension_value', CounterKey::MAX_VALUE_LENGTH),
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
    private static function string(string $name, int $length): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->create();
    }
}
