# phil/analytics-bundle

**Visits to your sites counted in daily aggregates, never tied to a person.**

phil/analytics-bundle keeps one counter per site, per UTC day, per metric and per value of a declared dimension.
Around that counter it decides which site a collection request belongs to and the declared route a path is counted
under. It also classifies the referrer, the device and the country, and respects Do Not Track and Global Privacy
Control. A keyed client digest serves rate limiting, and small numbers are suppressed when counters are read. Beyond
page views it counts goals, clicks, downloads, pages not found, forms, outbound links, pairs of routes, reading depth
and search terms, always as counters that stand alone. It keeps no account identifier, no IP address, no cookie and no
device identifier, and counts no unique visitor.

It is for applications that serve one or more sites and want their owners to see what is read, without tracking
visitors. Measuring in aggregates removes the personal data a visitor log would hold. The bundle installs no
analytics product, no referrer-parsing library and no device-detection library.

A page view reuses the robot or human verdict of `phil/bot-detection-bundle`, so robots stay out of the audience
counters. The collection script is a Stimulus controller that sets no cookie and stores nothing in the browser.

Registered and configured, the bundle collects: a collection endpoint, counters in two tables of a Doctrine
connection, a client digest under a salt drawn each UTC day and then forgotten, a Symfony Rate Limiter budget per site
and digest, events the application's server counts, the reading of dashboards over one site or several, and once a
day the retention of the counters, the reports due, the check of the goals that stopped being reached and the alert
on a site whose traffic spikes or dips. Each of these is an adapter behind a contract, and the application replaces any of them by naming its
own service in the configuration: its own store, its own projection of the sites, a country lookup, a list of
crawlers.

## Status

| Area | State |
|---|---|
| Counters, UTC days, sites (`CounterKey`, `UtcDay`, `SiteId`) | Implemented and tested |
| Declared routes (`DeclaredRoute`, `DeclaredRoutes`) | Implemented and tested |
| Origins and the collection projection's shape (`Origin`, `CollectionSite`, `SiteStatus`) | Implemented and tested |
| Admission of a page view (`AdmissionRule`, `Admission`) | Implemented and tested, over the `Verdict` of `phil/bot-detection-bundle` |
| Referrer classification (`HostList`, `ReferrerClassifier`, `ReferralKind`) | Implemented and tested |
| Device classes and countries (`DeviceClass`, `CountryCode`, `GeoDatabaseFreshness`) | Implemented and tested |
| Privacy signals (`PrivacySignals`) | Implemented and tested |
| Keyed client digest (`ClientDigester`, `ClientDigest`), over `phil/ip-anonymizer` | Implemented and tested |
| Small-number suppression (`SmallNumberThreshold`, `SmallNumberSuppression`, `SuppressedCounts`) | Implemented and tested |
| What a site measures (`MeasurePlan`, `ClosedList`, `Goal`, `DeclaredDimension`, `MeasureCounters`) | Implemented and tested |
| Who reads each link's count (`DeclaredOutboundLink`, `LinkCountReader`) | Implemented and tested |
| Events the server counts (`ServerEventCounter`), against each site's declaration | Implemented and tested |
| What a dashboard reads, for one site or summed over several (`CounterReader`, `ReaderScope`) | Implemented and tested |
| Retention (`CounterRetention`, `UtcMonth`) | Implemented and tested |
| Shared views, reports and alerts (`SharedView`, `ShareToken`, `ReportPeriod`, `TrafficAlertRule`) | Implemented and tested |
| `CounterStore`, `CollectionProjection`, `CollectionRateLimiter`, `CountryResolver`, `CrawlerDetector`, `OutboundLinkDirectory`, `CounterRollup` | Contracts, each with a default adapter the application can replace |
| `SharedViews`, `ReportSubscriptions`, `ReportSender`, `TrafficAlertNotifier` | Contracts, implemented by the application |
| Default adapters: `DbalCounterStore`, `DbalCounterRollup`, `CacheSaltStore`, `SymfonyCollectionRateLimiter`, `ConfiguredCollectionProjection`, `UserAgentCrawlerDetector`, `UnknownCountryResolver`, `NoOutboundLinks` | Implemented and tested |
| The collection endpoint (`Collector`, `CollectController`, route `phil_analytics_collect`) | Implemented and tested |
| The daily jobs on the schedule `phil_analytics`: retention, reports, broken flows with `phil/broken-flow-alerts`, traffic alerts (`TrafficAlertRun`) | Implemented and tested |
| `RobotVerdictCounter`, from `phil/bot-detection-bundle` | Port, for an application that also keeps that aggregate |
| The collection script, without cookie or local storage: a Stimulus controller (`assets/src/collect_controller.js`), with its second send on leaving a page | Implemented, tested with Node |
| `PhilAnalyticsBundle`, configuration `phil_analytics` | Implemented and tested |

## Documentation

- [Installation](doc/installation.md) — requirements, the optional packages of the default adapters, installing from
  a repository until the bundle is on Packagist, registering the bundle and importing its route.
- [Configuration](doc/configuration.md) — the tree of `phil_analytics`: the sites, each adapter by keyword or by the
  id of a service of the application, the endpoint and the jobs.
- [Counters](doc/counters.md) — `SiteId`, `UtcDay`, `CounterKey`: what is stored, why the day is the UTC day, why
  dimensions must be closed lists, and why visits replace unique visitors.
- [Declared routes](doc/declared-routes.md) — how a path is normalized into a declared route name or `(other)`,
  with the query and fragment dropped and placeholders never kept.
- [Collection and admission](doc/collection-and-admission.md) — which site a request belongs to, the fixed order
  of collection, the admission table, and the collection endpoint.
- [Measures](doc/measures.md) — clicks, goals and conversion, events in closed lists, outbound links, pairs of
  routes, reading depth, search terms, variants as a declared dimension, and events the server counts.
- [Retention](doc/retention.md) — daily counters for 13 months, then monthly totals.
- [Dashboards, shared views, reports and alerts](doc/sharing-reports-alerts.md) — what a dashboard reads within the
  sites its reader may see, share links, the public board, periodic reports, and alerts on a spike or a dip.
- [Scheduled jobs](doc/scheduled-jobs.md) — the retention, the reports due, the check of broken flows and the traffic
  alerts, once a day on the schedule `phil_analytics`.
- [Rate limiting](doc/rate-limiting.md) — the digest of an IPv4 address or IPv6 /64 under a salt that is forgotten,
  and the fail-closed limiter it feeds.
- [Sources and referrers](doc/sources-and-referrers.md) — the six source classes, the classification order, and
  host lists with the `{}` public-suffix label.
- [Devices and countries](doc/devices-and-countries.md) — three device classes from the user agent, the country
  looked up in memory, and a stale database failing to "unknown".
- [Privacy signals](doc/privacy-signals.md) — Do Not Track and Global Privacy Control: who sends `1` is counted
  nowhere.
- [Small-number suppression](doc/small-number-suppression.md) — grouping small counters into "other" on read,
  and never showing a group whose sum is under the threshold.
- [Extension points](doc/extension-points.md) — every contract, its invariants, with an example
  adapter.
- [Testing](doc/testing.md) — running the tests from the monorepo or with the bundle installed alone, and
  testing your adapters.
- [API reference](doc/reference.md) — every public type by layer, with its role and state.

## Quick start

```bash
composer require phil/analytics-bundle:^0.1@dev   # from a path or VCS repository until published
```

```yaml
# config/packages/phil_analytics.yaml
phil_analytics:
    sites:
        spring-con:
            origins: ['https://example.com']
            routes: { home: '/', talk: '/talks/{slug}', thanks: '/tickets/thanks' }
            goals: { ticket: { route: thanks } }

# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-endpoint-value="{{ url('phil_analytics_collect') }}">
```

Create the counter tables with a migration, and the page views of `https://example.com` are counted. To read them,
never show a counter under the threshold on its own:

```php
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\ValueObject\{SiteId, UtcDay};

$counts = $store->counts(SiteId::fromString('spring-con'), 'pageviews', 'route', UtcDay::fromString('2026-10-01'), UtcDay::fromString('2026-10-31'));
$shown = $suppression->apply($counts);              // SmallNumberSuppression, from the container
```

The full collection flow is in [doc/collection-and-admission.md](doc/collection-and-admission.md).

## Requirements

- PHP 8.5 or later
- `symfony/dependency-injection`, `symfony/config`, `symfony/http-foundation` and `symfony/rate-limiter` 8.2
- For the default adapters: Doctrine DBAL and DoctrineBundle; optionally Symfony Lock, Scheduler, Messenger, Notifier
  and `phil/broken-flow-alerts`
- `ext-sodium` and `ext-mbstring`
- `phil/ddd-bundle`, `phil/bot-detection-bundle` and `phil/ip-anonymizer` 0.1

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
