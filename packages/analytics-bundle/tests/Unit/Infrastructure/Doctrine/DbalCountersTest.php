<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterTables;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterRollup;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalCounterStore::class)]
#[CoversClass(DbalCounterRollup::class)]
#[CoversClass(CounterTables::class)]
final class DbalCountersTest extends TestCase
{
    private DbalCounterStore $store;

    private DbalCounterRollup $rollup;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $manager = $connection->createSchemaManager();

        foreach (CounterTables::configure($manager->introspectSchema())->getTables() as $table) {
            $manager->createTable($table);
        }

        $this->store = new DbalCounterStore($connection);
        $this->rollup = new DbalCounterRollup($connection);
    }

    public function testTheSchemaHasBothTablesOnce(): void
    {
        $schema = CounterTables::configure(CounterTables::configure(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema()));

        self::assertTrue($schema->hasTable(CounterTables::DAILY));
        self::assertTrue($schema->hasTable(CounterTables::MONTHLY));
        self::assertCount(2, $schema->getTables());
        self::assertSame(['site', 'month', 'metric', 'dimension', 'dimension_value', 'n'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $schema->getTable(CounterTables::MONTHLY)->getColumns()));
    }

    public function testTheColumnsAreSizedForTheirValues(): void
    {
        $schema = CounterTables::configure(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema());

        foreach ([CounterTables::DAILY => ['day' => 10], CounterTables::MONTHLY => ['month' => 7]] as $name => $period) {
            $lengths = [];

            foreach ($schema->getTable($name)->getColumns() as $column) {
                $lengths[$column->getObjectName()->toString()] = $column->getLength();
            }

            self::assertSame(['site' => SiteId::MAX_LENGTH, ...$period, 'metric' => 64, 'dimension' => 64, 'dimension_value' => CounterKey::MAX_VALUE_LENGTH, 'n' => null], $lengths, $name);
        }
    }

    public function testAnExistingDailyTableStillGetsItsMonthlyTable(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE '.CounterTables::DAILY.' (site VARCHAR(64))');

        $schema = CounterTables::configure($connection->createSchemaManager()->introspectSchema());

        self::assertTrue($schema->hasTable(CounterTables::MONTHLY));
        self::assertCount(1, $schema->getTable(CounterTables::DAILY)->getColumns());
    }

    public function testTheCountsReadAreIntegersByValue(): void
    {
        self::assertSame(['a' => 3, 'b' => 0, '7' => 2], DbalCounterStore::integers(['a' => '3', 'b' => null, 7 => 2]));
    }

    public function testAnIncrementAddsOneToItsCounterOnly(): void
    {
        $this->increment('2026-11-10', 'home');
        $this->increment('2026-11-10', 'home');
        $this->increment('2026-11-10', 'talk');
        $this->increment('2026-11-11', 'home');
        $this->store->increment(CounterKey::of(SiteId::fromString('other'), UtcDay::fromString('2026-11-10'), 'pageviews', 'route', 'home'));
        $this->store->increment(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-11-10'), 'visits', 'route', 'home'));

        self::assertSame(['home' => 2, 'talk' => 1], $this->counts('2026-11-10', '2026-11-10'));
        self::assertSame(['home' => 3, 'talk' => 1], $this->counts('2026-11-10', '2026-11-11'));
        self::assertSame(['home' => 1], $this->counts('2026-11-11', '2026-11-30'));
        self::assertSame([], $this->counts('2026-11-12', '2026-11-30'));
    }

    public function testANumericValueIsCountedAsText(): void
    {
        $this->store->increment(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-11-10'), 'engagement', 'scroll', '50'));

        $counts = $this->store->counts(SiteId::fromString('s'), 'engagement', 'scroll', UtcDay::fromString('2026-11-10'), UtcDay::fromString('2026-11-10'));

        // PHP turns the key "50" into the integer 50: the value is the text, whatever the array makes of its key.
        self::assertEquals(['50'], array_keys($counts));
        self::assertSame([1], array_values($counts));
    }

    public function testRetiringASiteDeletesItsDailyAndMonthlyCounters(): void
    {
        $this->increment('2025-01-10', 'home');
        $this->increment('2026-11-10', 'home');
        $this->store->increment(CounterKey::of(SiteId::fromString('other'), UtcDay::fromString('2026-11-10'), 'pageviews', 'route', 'home'));
        $this->store->increment(CounterKey::of(SiteId::fromString('other'), UtcDay::fromString('2025-01-10'), 'pageviews', 'route', 'home'));

        $this->rollup->rollUpBefore(UtcDay::fromString('2026-01-01'), SmallNumberThreshold::of(2));

        $this->store->deleteSite(SiteId::fromString('s'));

        self::assertSame(['home' => 1], $this->rollup->monthlyCounts(SiteId::fromString('other'), 'pageviews', 'route', UtcMonth::fromString('2025-01'), UtcMonth::fromString('2025-01')));

        self::assertSame([], $this->counts('2000-01-01', '2100-01-01'));
        self::assertSame([], $this->monthly('2025-01', '2025-12'));
        self::assertSame(['home' => 1], $this->store->counts(SiteId::fromString('other'), 'pageviews', 'route', UtcDay::fromString('2026-11-10'), UtcDay::fromString('2026-11-10')));
    }

    public function testTheDaysBeforeTheFirstDayKeptBecomeMonthlyTotals(): void
    {
        $this->increment('2025-09-30', 'home');
        $this->increment('2025-09-01', 'home');
        $this->increment('2025-08-15', 'home');
        $this->increment('2025-10-01', 'home');

        $this->rollup->rollUpBefore(UtcDay::fromString('2025-10-01'), SmallNumberThreshold::of(5));

        self::assertSame(['home' => 1], $this->counts('2000-01-01', '2100-01-01'));
        self::assertSame(['home' => 2], $this->monthly('2025-09', '2025-09'));
        self::assertSame(['home' => 3], $this->monthly('2025-08', '2025-09'));
        self::assertSame([], $this->monthly('2025-10', '2025-12'));
    }

    public function testARollupAddsToAMonthAlreadyRolledUp(): void
    {
        $this->increment('2025-09-10', 'home');
        $this->rollup->rollUpBefore(UtcDay::fromString('2025-09-15'), SmallNumberThreshold::of(5));
        $this->increment('2025-09-20', 'home');
        $this->increment('2025-09-21', 'home');
        $this->rollup->rollUpBefore(UtcDay::fromString('2025-10-01'), SmallNumberThreshold::of(5));

        self::assertSame(['home' => 3], $this->monthly('2025-09', '2025-09'));
    }

    public function testARareValueOfAnOpenDimensionIsForgotten(): void
    {
        $site = SiteId::fromString('s');

        foreach (['rare', 'common', 'common', 'common'] as $term) {
            $this->store->increment(CounterKey::of($site, UtcDay::fromString('2025-09-10'), 'search', 'term', $term));
        }

        $this->store->increment(CounterKey::of($site, UtcDay::fromString('2025-09-10'), 'pageviews', 'route', 'rare'));

        $this->rollup->rollUpBefore(UtcDay::fromString('2025-10-01'), SmallNumberThreshold::of(3));

        self::assertSame(['common' => 3], $this->rollup->monthlyCounts($site, 'search', 'term', UtcMonth::fromString('2025-09'), UtcMonth::fromString('2025-09')));
        self::assertSame(['rare' => 1], $this->rollup->monthlyCounts($site, 'pageviews', 'route', UtcMonth::fromString('2025-09'), UtcMonth::fromString('2025-09')));
    }

    public function testAMonthOfAnOpenDimensionUnderARaisedThresholdIsForgotten(): void
    {
        $site = SiteId::fromString('s');
        $term = static fn (string $day): CounterKey => CounterKey::of($site, UtcDay::fromString($day), 'search', 'term', 'rare');

        $this->store->increment($term('2025-09-10'));
        $this->store->increment($term('2025-09-11'));

        $this->rollup->rollUpBefore(UtcDay::fromString('2025-09-15'), SmallNumberThreshold::of(2));

        self::assertSame(['rare' => 2], $this->rollup->monthlyCounts($site, 'search', 'term', UtcMonth::fromString('2025-09'), UtcMonth::fromString('2025-09')));

        $this->store->increment($term('2025-09-20'));
        $this->rollup->rollUpBefore(UtcDay::fromString('2025-10-01'), SmallNumberThreshold::of(5));

        self::assertSame([], $this->rollup->monthlyCounts($site, 'search', 'term', UtcMonth::fromString('2025-09'), UtcMonth::fromString('2025-09')));
    }

    private function increment(string $day, string $route): void
    {
        $this->store->increment(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString($day), 'pageviews', 'route', $route));
    }

    /**
     * @return array<string, int>
     */
    private function counts(string $from, string $to): array
    {
        return $this->store->counts(SiteId::fromString('s'), 'pageviews', 'route', UtcDay::fromString($from), UtcDay::fromString($to));
    }

    /**
     * @return array<string, int>
     */
    private function monthly(string $from, string $to): array
    {
        return $this->rollup->monthlyCounts(SiteId::fromString('s'), 'pageviews', 'route', UtcMonth::fromString($from), UtcMonth::fromString($to));
    }
}
