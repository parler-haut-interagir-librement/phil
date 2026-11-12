# Extension points

The bundle decides what the design decides, and leaves to the application what each application decides for
itself through **contracts**: PHP interfaces under `Phil\AnalyticsBundle\Application\Contract`, plus one interface reused
from `phil/bot-detection-bundle`. The bundle wires a default adapter for most of them; the application replaces one by
naming its own service in the configuration, and implements the others itself ([configuration.md](configuration.md#the-adapters)).

| Contract | Method(s) | Used for | Default adapter, configuration key |
|---|---|---|---|
| `CounterStore` | `increment()`, `counts()`, `deleteSite()` | Where counters live | `DbalCounterStore`, `counters.store` |
| `CollectionProjection` | `siteDeclaring()`, `site()` | Which site an origin belongs to, its routes, status and plan | `ConfiguredCollectionProjection`, `collection.projection` |
| `CollectionRateLimiter` | `consume()` | Bounding collection per site and digest | `SymfonyCollectionRateLimiter`, `rate_limiter.limiter` |
| `CountryResolver` | `countryOf()` | The country of an address, in memory | `UnknownCountryResolver`, `collection.country_resolver` |
| `CrawlerDetector` | `isDeclaredCrawler()` | Crawlers that declare themselves | `UserAgentCrawlerDetector`, `collection.crawler_detector` |
| `OutboundLinkDirectory` | `find()` | The links written into authors' pages | `NoOutboundLinks`, `collection.outbound_links` |
| `CounterRollup` | `rollUpBefore()`, `monthlyCounts()` | Daily counters past their retention, and monthly totals | `DbalCounterRollup`, `counters.rollup` |
| `ExpiringSaltStore` (from `phil/ip-anonymizer`) | `claim()` | The salt of the day, shared by the servers | `CacheSaltStore`, `salt.store` |
| `SharedViews` | `save()`, `linkOfTokenDigest()`, `publicBoardOf()` | Share links and the public board | none |
| `ReportSubscriptions` | `ofCadence()` | Who receives which report | none, `reports.subscriptions` |
| `ReportSender` | `send()` | Sending one report | none, `reports.sender` |
| `TrafficAlertNotifier` | `notify()` | Telling someone about a spike or a dip | none, `traffic_alerts.notifier` |
| `RobotVerdictCounter` (from `phil/bot-detection-bundle`) | `increment()` | The aggregate count of robot verdicts | none; `Collector` counts per site in the counter store |

A replacement is named in the configuration, and the bundle aliases the contract to it:

```yaml
phil_analytics:
    collection:
        country_resolver: App\Analytics\GeoIpCountryResolver
```

The examples below are sketches of replacements in application code. The libraries they use (Doctrine DBAL, Symfony
Rate Limiter, GeoIP2, Crawler-Detect) are the application's dependencies, not the bundle's.

## `CounterStore`

```php
namespace Phil\AnalyticsBundle\Application\Contract;

interface CounterStore
{
    public function increment(CounterKey $key): void;

    /** @return array<string, int> */
    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array;

    public function deleteSite(SiteId $site): void;
}
```

| Method | Contract |
|---|---|
| `increment()` | **A single atomic upsert**, so concurrent page views never lose a count. Stores a number per key and nothing that produced it: no instant finer than the day, no address, no user agent, no referrer URL |
| `counts()` | Counters of one metric and one dimension, over an **inclusive** range of days, **summed per dimension value**, keyed by value. No suppression: the caller applies `SmallNumberSuppression` |
| `deleteSite()` | Deletes every counter of the site, when it is retired |

```php
final readonly class DbalCounterStore implements CounterStore
{
    public function __construct(private Connection $db) {}

    public function increment(CounterKey $key): void
    {
        $this->db->executeStatement(
            'INSERT INTO analytics_counter (site, day, metric, dimension, value, n)
             VALUES (?, ?, ?, ?, ?, 1)
             ON CONFLICT (site, day, metric, dimension, value) DO UPDATE SET n = analytics_counter.n + 1',
            [$key->site->value, $key->day->value, $key->metric, $key->dimension, $key->value],
        );
    }

    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
    {
        return array_map('intval', $this->db->fetchAllKeyValue(
            'SELECT value, SUM(n) FROM analytics_counter
             WHERE site = ? AND metric = ? AND dimension = ? AND day BETWEEN ? AND ?
             GROUP BY value',
            [$site->value, $metric, $dimension, $from->value, $to->value],
        ));
    }

    public function deleteSite(SiteId $site): void
    {
        $this->db->executeStatement('DELETE FROM analytics_counter WHERE site = ?', [$site->value]);
    }
}
```

The collection process only needs `INSERT`, `UPDATE` and `SELECT` on the counter table (the upsert reads the value
it increments); deletion and dashboards run elsewhere, with their own role.

## `CollectionProjection`

```php
interface CollectionProjection
{
    /** The site that declares this origin, or null: the request is then refused without a write. */
    public function siteDeclaring(Origin $origin): ?CollectionSite;

    /** The site of this reference, or null: what the server counts is checked against the same declaration. */
    public function site(SiteId $site): ?CollectionSite;
}
```

Invariants:

- **Collection reads this projection, never the application that maintains it.** The application writes it when
  a site's declaration changes and when a site is suspended, reinstated or retired; how it writes is not part of
  the port.
- It holds no personal information: origins, routes, status (the shape of `CollectionSite`).
- An origin belongs to at most one site. Compare with `Origin::equals()` or by the normalized `toString()`.
- `site()` returns the same `CollectionSite` as `siteDeclaring()` for any of its origins: `ServerEventCounter` reads
  it, so that an event the server counts follows the declaration and the status a page view follows.

```php
final readonly class DbalCollectionProjection implements CollectionProjection
{
    public function __construct(private Connection $db) {}

    public function siteDeclaring(Origin $origin): ?CollectionSite
    {
        // analytics_collection_site(site, routes_json, status); analytics_collection_origin(origin, site)
        $row = $this->db->fetchAssociative(
            'SELECT s.site, s.routes_json, s.status
             FROM analytics_collection_origin o JOIN analytics_collection_site s ON s.site = o.site
             WHERE o.origin = ?',
            [$origin->toString()],
        );

        if (false === $row) {
            return null;
        }

        $origins = $this->db->fetchFirstColumn(
            'SELECT origin FROM analytics_collection_origin WHERE site = ?',
            [$row['site']],
        );

        return CollectionSite::of(
            SiteId::fromString($row['site']),
            array_map(Origin::fromString(...), $origins),
            DeclaredRoutes::of(array_map(
                static fn (array $route): DeclaredRoute => DeclaredRoute::of($route['name'], $route['pattern']),
                json_decode($row['routes_json'], true, flags: \JSON_THROW_ON_ERROR),
            )),
            SiteStatus::from($row['status']),
        );
    }

    public function site(SiteId $site): ?CollectionSite
    {
        $origin = $this->db->fetchOne('SELECT origin FROM analytics_collection_origin WHERE site = ? LIMIT 1', [$site->value]);

        return false === $origin ? null : $this->siteDeclaring(Origin::fromString($origin));
    }
}
```

The storage layout is yours. What matters is that every declared origin of the site is returned, so the
classifier can recognize internal referrers, and that origins are stored in their normalized form
(`Origin::toString()`).

## `CollectionRateLimiter`

```php
interface CollectionRateLimiter
{
    public function consume(SiteId $site, ClientDigest $digest): bool;
}
```

Invariants: **fails closed** (returns `false` or throws when its backend cannot answer); keeps the digest, never
the address; a time to live no longer than the budget window; a non-persistent store; a budget calibrated on a
group behind one address. See [rate-limiting.md](rate-limiting.md).

```php
final readonly class SymfonyCollectionRateLimiter implements CollectionRateLimiter
{
    public function __construct(private RateLimiterFactoryInterface $collectionLimiter) {}

    public function consume(SiteId $site, ClientDigest $digest): bool
    {
        try {
            return $this->collectionLimiter->create($site->value.'|'.$digest->toString())->consume()->isAccepted();
        } catch (\Throwable) {
            return false; // fail closed
        }
    }
}
```

## `CountryResolver`

```php
interface CountryResolver
{
    public function countryOf(#[\SensitiveParameter] string $ip): CountryCode;
}
```

Invariants: **the address is used and forgotten** — neither logged nor stored; answers `CountryCode::unknown()`
when it cannot tell, including when its database is past the age its licence allows (`GeoDatabaseFreshness`);
never throws for an address it cannot place.

```php
final readonly class GeoIpCountryResolver implements CountryResolver
{
    public function __construct(
        private \GeoIp2\Database\Reader $reader,
        private GeoDatabaseFreshness $freshness,
        private ClockInterface $clock,
    ) {}

    public function countryOf(#[\SensitiveParameter] string $ip): CountryCode
    {
        $builtAt = new \DateTimeImmutable('@'.$this->reader->metadata()->buildEpoch);

        if (!$this->freshness->isUsable($builtAt, $this->clock->now())) {
            return CountryCode::unknown();
        }

        try {
            $code = $this->reader->country($ip)->country->isoCode;
        } catch (\Throwable) {
            return CountryCode::unknown();
        }

        return null === $code ? CountryCode::unknown() : CountryCode::fromString($code);
    }
}
```

## `CrawlerDetector`

```php
interface CrawlerDetector
{
    public function isDeclaredCrawler(string $userAgent): bool;
}
```

Only crawlers that **declare** themselves are this port's concern; the others are left to the browser's robot
verdict and the rate limiter. An MIT-licensed crawler list such as `jaybizzle/crawler-detect` is the intended
source.

```php
final readonly class CrawlerDetectAdapter implements CrawlerDetector
{
    public function isDeclaredCrawler(string $userAgent): bool
    {
        return new \Jaybizzle\CrawlerDetect\CrawlerDetect()->isCrawler($userAgent);
    }
}
```

## `OutboundLinkDirectory`

```php
interface OutboundLinkDirectory
{
    public function find(SiteId $site, string $reference): ?DeclaredOutboundLink;
}
```

Invariants: answers only for links the application itself wrote into a page; `null` for anything else, so a forged
reference counts the host only. The reference is opaque and is what the page puts in `data-phil-analytics-link`; never
make it the URL, which may hold a token. See [measures.md](measures.md#outbound-links).

## `CounterRollup`

```php
interface CounterRollup
{
    public function rollUpBefore(UtcDay $firstDailyDay, SmallNumberThreshold $threshold): void;

    /** @return array<string, int> */
    public function monthlyCounts(SiteId $site, string $metric, string $dimension, UtcMonth $from, UtcMonth $to): array;
}
```

Invariants: `rollUpBefore()` adds the days before the bound to their months and deletes them **in one transaction**, so
a count is never in both or in neither; running it twice changes nothing; the monthly total of a dimension of
`MeasureNames::OPEN_DIMENSIONS` under the threshold is not kept. See [retention.md](retention.md).

## `SharedViews`

```php
interface SharedViews
{
    public function save(SharedView $view): void;

    public function linkOfTokenDigest(string $tokenDigest): ?SharedView;

    public function publicBoardOf(SiteId $site): ?SharedView;
}
```

Invariants: stores the token's digest, never the token; a lookup is by digest only. See
[sharing-reports-alerts.md](sharing-reports-alerts.md).

## `ReportSubscriptions`, `ReportSender`, `TrafficAlertNotifier`

```php
interface ReportSubscriptions
{
    /** @return iterable<ReportSubscription> */
    public function ofCadence(ReportCadence $cadence): iterable;
}

interface ReportSender
{
    public function send(ReportSubscription $subscription, ReportPeriod $period): void;
}

interface TrafficAlertNotifier
{
    public function notify(TrafficAlert $alert): void;
}
```

Invariants: a report reads the counters through `SmallNumberSuppression` and leaves out
`MeasureNames::PRIVATE_METRICS`; a message queued for a sender holds the subscription's identifier and the period, and
the recipient is resolved when the report is sent.

## The robot-verdict counter

`Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter` is reused rather than redeclared:

```php
interface RobotVerdictCounter
{
    public function increment(): void;
}
```

Its `increment()` takes **no argument**, so nothing identifying can reach it — not even the site. `Collector` does not
use it: it counts robot verdicts per site and UTC day in the counter store, under `robot_verdicts`. An application that
also keeps this aggregate wires **one counter per site**, never a site argument, and increments it only when
`Admission::advancesRobotVerdictCounter()` is true. Report its count outside the analytics aggregates (an alerting
dashboard, a metrics gauge): a count that drops to zero for a site that usually has some is how a silent detector
shows. See the bot-detection bundle's documentation for adapters.

## Testing adapters

See [testing.md](testing.md#testing-your-adapters).
