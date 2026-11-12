<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;

/**
 * The counters in {@see CounterTables::DAILY}, on any platform Doctrine DBAL supports.
 *
 * An increment is an `UPDATE ... SET n = n + 1`; when no row exists yet, an `INSERT` of one, and when a concurrent
 * request inserted it first — the primary key refuses the second —, the `UPDATE` again. No count is lost and the role
 * of the collection process needs nothing beyond `SELECT`, `INSERT` and `UPDATE` on the table.
 */
final readonly class DbalCounterStore implements CounterStore
{
    private const string WHERE_KEY = 'site = ? AND day = ? AND metric = ? AND dimension = ? AND dimension_value = ?';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function increment(CounterKey $key): void
    {
        $values = [$key->site->value, $key->day->value, $key->metric, $key->dimension, $key->value];

        if ($this->add($values) > 0) {
            return;
        }

        try {
            $this->connection->insert(CounterTables::DAILY, [
                'site' => $key->site->value,
                'day' => $key->day->value,
                'metric' => $key->metric,
                'dimension' => $key->dimension,
                'dimension_value' => $key->value,
                'n' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->add($values);
        }
    }

    #[Override]
    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT dimension_value, SUM(n) FROM '.CounterTables::DAILY.' WHERE site = ? AND metric = ? AND dimension = ? AND day >= ? AND day <= ? GROUP BY dimension_value ORDER BY dimension_value',
            [$site->value, $metric, $dimension, $from->value, $to->value],
        );

        return self::integers($rows);
    }

    #[Override]
    public function deleteSite(SiteId $site): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($site): void {
            $connection->delete(CounterTables::DAILY, ['site' => $site->value]);
            $connection->delete(CounterTables::MONTHLY, ['site' => $site->value]);
        });
    }

    /**
     * @param array<mixed> $rows
     *
     * @return array<string, int>
     */
    public static function integers(array $rows): array
    {
        $counts = [];

        foreach ($rows as $value => $count) {
            $counts[(string) $value] = is_numeric($count) ? (int) $count : 0;
        }

        return $counts;
    }

    /**
     * @param list<string> $values
     */
    private function add(array $values): int
    {
        return (int) $this->connection->executeStatement('UPDATE '.CounterTables::DAILY.' SET n = n + 1 WHERE '.self::WHERE_KEY, $values);
    }
}
