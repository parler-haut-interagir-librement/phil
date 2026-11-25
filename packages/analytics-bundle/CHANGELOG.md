# Changelog

All notable changes to `phil/analytics-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-24

Every `Sec-GPC` header of a request is read. See
[Privacy signals](doc/privacy-signals.md).

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4).

### Fixed

- A browser that sent the Global Privacy Control in a second `Sec-GPC` header, or in a list such as `Sec-GPC: 0, 1`,
  was counted: `CollectController` joins every occurrence of the header with commas, and `PrivacySignals::fromHeaders()`
  reads one trimmed value `1` among them as the signal. `DNT` is still read as a single value.

## 0.2.0-alpha - 2026-11-18

A metric is read only in its canonical form, and the keys of the counter tables
compare byte by byte on MySQL and MariaDB. See [Upgrading from 0.1](doc/counters.md#upgrading-from-01).

### Added

- `MeasureNames::METRICS`, `MeasureNames::NAMED_METRICS` and `MeasureNames::isMetric()`: whether a metric is one the
  bundle writes, in its exact form, one of `METRICS` or `goals.<goal>` and `server_events.<event>`.
- `CounterTables::configure()` takes the platform of the connection as its second argument.

### Changed

- `CounterReader::read()` and `readAcross()` throw `InvalidValue` for a metric that `MeasureNames::isMetric()` refuses
  and for a dimension that is not a name of `CounterKey::NAME_PATTERN`, before any store is asked.
- `SharedView::mayShow()` refuses a metric that `MeasureNames::isMetric()` refuses.
- On MySQL and MariaDB, the columns `site`, `metric`, `dimension` and `dimension_value` of `phil_analytics_counter`
  and `phil_analytics_monthly_counter` are `utf8mb4` with the `utf8mb4_bin` collation; `doctrine:migrations:diff`
  writes the migration of an existing install.

### Security

- A reader allowed to read a site but not the count of each outbound link could read it under a case variant of its
  metric: `CounterReader` refused `outbound_link` by an exact comparison, then handed `OUTBOUND_LINK` to the store,
  whose `WHERE metric = ?` matched the private rows under the default case-insensitive collation of MySQL and MariaDB.
  The reader and `SharedView::mayShow()` now refuse any metric outside the canonical names, and the keys of the
  counters compare byte by byte.

## 0.1.1-alpha - 2026-11-14

### Security

- `rate_limiter.lock_factory` defaults to `lock.factory`, taken when it is defined, as FrameworkBundle does: the
  collection limiter reserves under it, so that two sends of one client that read the same window can no longer both
  pass its last place. `null` keeps reserving without a lock.

## 0.1.0-alpha - 2026-11-12

### Added

- Counters, UTC days, sites (`CounterKey`, `UtcDay`, `SiteId`).
- Declared routes (`DeclaredRoute`, `DeclaredRoutes`): a path is tried only against the routes of its segment count,
  and `DeclaredRoute` counts its literal segments once; `DeclaredRoute::length()` gives the segment count.
- Origins and the collection projection's shape (`Origin`, `CollectionSite`, `SiteStatus`, with the `MeasurePlan` of
  the site). An origin is read by PHP's WHATWG URL parser, as a browser reads it: an international host in its ASCII
  form, user information refused, a backslash ending the host of a `Referer`.
- Admission of a page view (`AdmissionRule`, `Admission`) — implemented and tested, over the `Verdict` of
  `phil/bot-detection-bundle`.
- The client digest of the limiter (`ClientDigester`, `ClientDigest`), a `Pipeline` of `phil/ip-anonymizer` running
  `Truncation(32, 64)` then `KeyedHash` under a `SaltProvider`.
- Referrer classification (`HostList`, `ReferrerClassifier`, `ReferralKind`). `HostList` keeps its literal entries
  in a hash set, looked up once per suffix of the host, and its `{}` entries in a tree of labels the host walks down
  from the right, so a list of thousands of `{}` entries matches as a short one does.
- Device classes and countries (`DeviceClass`, `CountryCode`, `GeoDatabaseFreshness`).
- Privacy signals (`PrivacySignals`).
- Small-number suppression (`SmallNumberThreshold`, `SmallNumberSuppression`, `SuppressedCounts`): the "other" group
  shows only when its sum meets the threshold, and a group of several counters whose sum is under it is hidden,
  since its sum gives its counters away.
- What a site measures (`MeasurePlan`, `ClosedList`, `DeclaredDimension`, `MeasureEvent`, `MeasureNames`,
  `MeasureCounters`), each value checked against a closed list: clicks in aggregate by default (`ClickTracking`), goals
  and conversion per visit over small numbers hidden (`Goal`, `ConversionRate`), downloads, pages not found and forms,
  pairs of routes (`RouteTransition`), reading depth and active time in bands (`ScrollDepth`, `ActiveTime`), search
  terms (`SearchTerm`), and variants as a declared dimension.
- Outbound links to any host (`OutboundHost`), and the count of each link an application wrote into a page, read by
  its author and the administrators only (`DeclaredOutboundLink`, `LinkCountReader`).
- Events the application's server counts (`ServerEventCounter`, `MeasureNames::SERVER_EVENTS`), each a value of the
  site's closed list `server_events`, with its declared dimensions; `CollectionProjection::site()` reads a site by its
  reference.
- What a dashboard reads (`CounterReader`, `ReaderScope`): one metric over at most 400 days, for one site or summed
  over several, under the small-number rule; a site outside the reader's scope reads as a site that does not exist.
- Daily counters kept 13 months, then monthly totals (`CounterRetention`, `UtcMonth`).
- Share links and a public board, revocable (`ShareToken`, `SharedView`), reports (`ReportCadence`, `ReportPeriod`,
  `ReportSubscription`) and alerts on a spike or a dip (`TrafficAlertRule`, `TrafficAlert`, `TrafficAlertKind`,
  `TrafficAlertRun`).
- `CounterStore`, `CollectionProjection`, `CollectionRateLimiter`, `CountryResolver`, `CrawlerDetector`,
  `OutboundLinkDirectory`, `CounterRollup`, `SharedViews`, `ReportSubscriptions`, `ReportSender` and
  `TrafficAlertNotifier` (contracts); `RobotVerdictCounter`, from `phil/bot-detection-bundle` (reused rather than
  redeclared).
- The configuration `phil_analytics` (`AnalyticsConfiguration`, `AnalyticsServices`): the small-number threshold, the
  search, social and spam host lists with defaults, the sites, and each adapter named by a keyword for the bundle's
  own or by the id of a service of the application, which the contract is then aliased to; per site, its goals,
  forms, click names, click level, download extensions, dimensions and server events.
- Default adapters: `DbalCounterStore` and `DbalCounterRollup` in two tables of a Doctrine connection
  (`CounterTables`, added to the ORM's schema by `CounterSchemaListener`), an increment as an `UPDATE`, else an
  `INSERT` retried as an `UPDATE` on a concurrent insert; `CacheSaltStore`, the salt of each UTC day in a PSR-6 pool
  under an optional lock, for `ForgottenSalt`; `SymfonyCollectionRateLimiter`, a sliding window of Symfony Rate Limiter
  per site and digest that fails closed; `ConfiguredCollectionProjection`, the sites of the configuration;
  `UserAgentCrawlerDetector`, `UnknownCountryResolver` and `NoOutboundLinks`. Without DoctrineBundle and without a
  store named, nothing that counts is wired. In the `prod` environment, the build refuses a salt or limiter pool local
  to each server, through `RateLimitGuard` of phil/rate-limit, unless `single_server` is set.
- The collection endpoint: `Collector`, the order of collection from a `CollectionRequest` to a `CollectionOutcome`,
  a body read up to 4096 bytes and decoded to a depth of three; `CollectController` and the stateless route
  `phil_analytics_collect` (`config/routes.php`), `POST` only, answered without a body. Robot verdicts are counted per
  site and day under `MeasureNames::ROBOT_VERDICTS`.
- The daily jobs on the Symfony Scheduler schedule `phil_analytics` (`AnalyticsSchedule`), at a configured UTC time:
  the retention (`CounterRetentionRun`), the reports due on a Monday or a first (`DueReports`), and the check of the
  watched goals with `phil/broken-flow-alerts` (`GoalCompletionHistory`), alerts sent through Symfony Notifier unless
  an `AlertSender` is named, and the traffic alerts of the watched sites (`traffic_alerts`), sent to a
  `TrafficAlertNotifier`; messages without content (`ApplyCounterRetention`, `SendDueReports`, `CheckBrokenFlows`,
  `CheckTrafficAlerts`).
- The collection script: the Stimulus controller `collect` (`assets/src/collect_controller.js`) and its page view
  (`assets/src/collect.js`), tested under Node's test runner (`tests/js`). A page Turbo renders after the first one
  sends the previous page as its referrer, so browsing with Turbo counts one visit; the script sends the referrer's
  origin only, never its path, by a `fetch` kept alive without credentials, the beacon only without `fetch`. It reports
  clicks, downloads, outbound links, declared forms, pages not found and searches, and sends how far a page was read
  and for how long once, on `pagehide` or when Turbo replaces the page.
- The documentation in `doc/`, one file per topic, indexed by the README, and its French mirror in `README.fr.md` and
  `doc/fr/`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
