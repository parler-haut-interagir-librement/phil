<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Phil\AnalyticsBundle\Application\Contract\CounterRollup;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;

/**
 * The rollup of {@see CounterTables::DAILY} into {@see CounterTables::MONTHLY}, in one transaction: the daily rows
 * before the first day kept are summed per month by the database, added to the monthly totals, then deleted.
 */
final readonly class DbalCounterRollup implements CounterRollup
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function rollUpBefore(UtcDay $firstDailyDay, SmallNumberThreshold $threshold): void
    {
        $this->connection->transactional(function (Connection $connection) use ($firstDailyDay, $threshold): void {
            $month = $connection->getDatabasePlatform()->getSubstringExpression('day', '1', '7');
            $sums = $connection->fetchAllAssociative(
                \sprintf('SELECT site, %1$s AS month, metric, dimension, dimension_value, SUM(n) AS n FROM %2$s WHERE day < ? GROUP BY site, %1$s, metric, dimension, dimension_value', $month, CounterTables::DAILY),
                [$firstDailyDay->value],
            );

            foreach ($sums as $sum) {
                $this->addToMonth($connection, $sum, $threshold);
            }

            $connection->executeStatement('DELETE FROM '.CounterTables::DAILY.' WHERE day < ?', [$firstDailyDay->value]);
        });
    }

    #[Override]
    public function monthlyCounts(SiteId $site, string $metric, string $dimension, UtcMonth $from, UtcMonth $to): array
    {
        return DbalCounterStore::integers($this->connection->fetchAllKeyValue(
            'SELECT dimension_value, SUM(n) FROM '.CounterTables::MONTHLY.' WHERE site = ? AND metric = ? AND dimension = ? AND month >= ? AND month <= ? GROUP BY dimension_value ORDER BY dimension_value',
            [$site->value, $metric, $dimension, $from->toString(), $to->toString()],
        ));
    }

    /**
     * A month's total of an open dimension under the threshold is forgotten: a rare search term or host is not kept.
     *
     * @param array<string, mixed> $sum
     */
    private function addToMonth(Connection $connection, array $sum, SmallNumberThreshold $threshold): void
    {
        $key = [
            'site' => $this->text($sum['site'] ?? null),
            'month' => $this->text($sum['month'] ?? null),
            'metric' => $this->text($sum['metric'] ?? null),
            'dimension' => $this->text($sum['dimension'] ?? null),
            'dimension_value' => $this->text($sum['dimension_value'] ?? null),
        ];
        $existing = $connection->fetchOne(
            'SELECT n FROM '.CounterTables::MONTHLY.' WHERE site = ? AND month = ? AND metric = ? AND dimension = ? AND dimension_value = ?',
            array_values($key),
        );
        $total = (false === $existing ? 0 : $this->integer($existing)) + $this->integer($sum['n'] ?? null);

        if (\in_array($key['dimension'], MeasureNames::OPEN_DIMENSIONS, true) && !$threshold->isMetBy($total)) {
            $connection->delete(CounterTables::MONTHLY, $key);

            return;
        }

        if (false === $existing) {
            $connection->insert(CounterTables::MONTHLY, [...$key, 'n' => $total]);
        } else {
            $connection->update(CounterTables::MONTHLY, ['n' => $total], $key);
        }
    }

    /**
     * The key columns are text: every platform reads them as strings.
     */
    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
