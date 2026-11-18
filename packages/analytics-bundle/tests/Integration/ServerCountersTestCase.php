<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\AnalyticsBundle\Application\Reading\CounterReader;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Reading\ReaderScope;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterTables;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterRollup;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\TestCase;

/**
 * The counters on a real server read from the environment: keys that differ only by their case or accents are never
 * one counter, and a variant of the private metric reads nothing, neither from the reader nor from the store itself.
 */
abstract class ServerCountersTestCase extends TestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testAVariantOfThePrivateMetricReadsNothingFromTheStore(): void
    {
        $store = new DbalCounterStore($this->connection());
        $store->increment($this->key('spring-con', MeasureNames::OUTBOUND_LINK, 'link', 'ticket-page'));

        foreach (['OUTBOUND_LINK', 'Outbound_link', 'outbound_lınk'] as $variant) {
            self::assertSame([], $store->counts(SiteId::fromString('spring-con'), $variant, 'link', $this->day(), $this->day()), $variant);
        }

        self::assertSame(['ticket-page' => 1], $store->counts(SiteId::fromString('spring-con'), MeasureNames::OUTBOUND_LINK, 'link', $this->day(), $this->day()));
    }

    public function testTheReaderRefusesTheVariantBeforeTheStore(): void
    {
        $store = new DbalCounterStore($this->connection());

        for ($i = 0; $i < 10; ++$i) {
            $store->increment($this->key('spring-con', MeasureNames::OUTBOUND_LINK, 'link', 'ticket-page'));
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"OUTBOUND_LINK" is not a metric the bundle writes.');

        new CounterReader($store, new SmallNumberSuppression(SmallNumberThreshold::of(5)))
            ->read(ReaderScope::everySite(), SiteId::fromString('spring-con'), 'OUTBOUND_LINK', 'link', $this->day(), $this->day());
    }

    public function testSitesAndValuesThatDifferByCaseOrAccentAreCountedApart(): void
    {
        $store = new DbalCounterStore($this->connection());

        foreach (['Acme', 'acme', 'ACME', 'acmé'] as $site) {
            $store->increment($this->key($site, MeasureNames::PAGEVIEWS, 'route', 'home'));
        }

        foreach (['Rose', 'rose', 'rosé'] as $value) {
            $store->increment($this->key('acme', MeasureNames::SEARCH, 'term', $value));
            $store->increment($this->key('acme', MeasureNames::SEARCH, 'term', $value));
        }

        foreach (['Acme', 'ACME', 'acmé'] as $site) {
            self::assertSame(['home' => 1], $store->counts(SiteId::fromString($site), MeasureNames::PAGEVIEWS, 'route', $this->day(), $this->day()), $site);
        }

        self::assertSame(['home' => 1], $store->counts(SiteId::fromString('acme'), MeasureNames::PAGEVIEWS, 'route', $this->day(), $this->day()));
        self::assertSame(['Rose' => 2, 'rose' => 2, 'rosé' => 2], $store->counts(SiteId::fromString('acme'), MeasureNames::SEARCH, 'term', $this->day(), $this->day()));
    }

    public function testTheMonthlyTotalsKeepTheVariantsApart(): void
    {
        $connection = $this->connection();
        $store = new DbalCounterStore($connection);

        foreach (['Rose', 'rose'] as $value) {
            for ($i = 0; $i < 3; ++$i) {
                $store->increment($this->key('acme', MeasureNames::PAGEVIEWS, 'route', $value));
            }
        }

        $rollup = new DbalCounterRollup($connection);
        $rollup->rollUpBefore(UtcDay::fromString('2026-12-01'), SmallNumberThreshold::of(2));

        self::assertSame(['Rose' => 3, 'rose' => 3], $rollup->monthlyCounts(SiteId::fromString('acme'), MeasureNames::PAGEVIEWS, 'route', UtcMonth::fromString('2026-11'), UtcMonth::fromString('2026-11')));
        self::assertSame([], $rollup->monthlyCounts(SiteId::fromString('acme'), 'PAGEVIEWS', 'route', UtcMonth::fromString('2026-11'), UtcMonth::fromString('2026-11')));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->connection = $connection;

        foreach ([CounterTables::DAILY, CounterTables::MONTHLY] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        // Only the counters' own tables: the server is shared with the tests of other packages.
        $manager = $connection->createSchemaManager();

        foreach (CounterTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform())->getTables() as $table) {
            $manager->createTable($table);
        }

        return $connection;
    }

    private function key(string $site, string $metric, string $dimension, string $value): CounterKey
    {
        return CounterKey::of(SiteId::fromString($site), $this->day(), $metric, $dimension, $value);
    }

    private function day(): UtcDay
    {
        return UtcDay::fromString('2026-11-10');
    }
}
