# API reference

Every public type of the bundle, by layer, with its role and state. Namespaces are relative to
`Phil\AnalyticsBundle`. All value objects are `final readonly`, built by named constructors, and throw
`Phil\DddBundle\Domain\Exception\InvalidValue` on an invalid value.

## Bundle

| Type | Role | State |
|---|---|---|
| `PhilAnalyticsBundle` | The bundle class; extension alias `phil_analytics` | Implemented and tested ([configuration.md](configuration.md)) |

## Domain — value objects (`Domain\ValueObject`)

| Type | Role | State |
|---|---|---|
| `SiteId` | Opaque reference of a measured site; `fromString()`, `equals()`, `toString()`; `MAX_LENGTH` = 255 | Implemented and tested ([counters.md](counters.md)) |
| `UtcDay` | The UTC day of a counter; `of()`, `fromString()`, `start()`, `next()`, `isBefore()`, `equals()`, `toString()` | Implemented and tested ([counters.md](counters.md)) |
| `CounterKey` | Site, day, metric, dimension, value; `of()`, `equals()`; `OTHER`, `UNKNOWN`, `NAME_PATTERN`, `MAX_VALUE_LENGTH` | Implemented and tested ([counters.md](counters.md)) |
| `DeclaredRoute` | A route name and path pattern; `of()`, `matches()`, `specificity()`, `length()`, `split()`, `hasSameShapeAs()` | Implemented and tested ([declared-routes.md](declared-routes.md)) |
| `DeclaredRoutes` | A site's closed list of routes; `of()`, `none()`, `normalize()` | Implemented and tested ([declared-routes.md](declared-routes.md)) |
| `Origin` | A normalized http(s) origin; `fromString()`, `ofUrl()`, `ofRequest()`, `equals()`, `toString()` | Implemented and tested ([collection-and-admission.md](collection-and-admission.md)) |
| `CollectionSite` | A site's origins, routes and status, the shape of the collection projection; `of()`, `declares()` | Implemented and tested ([collection-and-admission.md](collection-and-admission.md)) |
| `HostList` | Host names a referrer host is matched against, with the `{}` public-suffix label; `of()`, `empty()`, `matches()` | Implemented and tested ([sources-and-referrers.md](sources-and-referrers.md)) |
| `CountryCode` | An ISO 3166-1 alpha-2 code or unknown; `fromString()`, `unknown()`, `isKnown()`, `equals()`, `toString()` | Implemented and tested ([devices-and-countries.md](devices-and-countries.md)) |
| `PrivacySignals` | Do Not Track and Global Privacy Control; `fromHeaders()`, `none()`, `optOut()` | Implemented and tested ([privacy-signals.md](privacy-signals.md)) |
| `ClientDigest` | The digest a limiter keeps, `<salt id>:<hex>`; `fromString()`, `equals()`, `toString()` | Implemented and tested ([rate-limiting.md](rate-limiting.md)) |
| `UtcMonth` | The month of a cumulative counter; `of()`, `fromString()`, `minus()`, `firstDay()`, `toString()` | Implemented and tested ([retention.md](retention.md)) |
| `SmallNumberThreshold` | The count under which a counter is never shown alone; `of()` (at least 2), `isMetBy()` | Implemented and tested ([small-number-suppression.md](small-number-suppression.md)) |

## Domain — rules and enums

| Type | Role | State |
|---|---|---|
| `Domain\Collection\SiteStatus` | `Active`, `Suspended`, `Retired`; `collects()` | Implemented and tested |
| `Domain\Collection\Admission` | What a page view does; `advancesAudience()`, `advancesRobotVerdictCounter()`, `isRefusal()` | Implemented and tested |
| `Domain\Collection\AdmissionRule` | `decide(SiteStatus, PrivacySignals, bool $declaredCrawler, ReferralKind, Verdict): Admission`, in a fixed order | Implemented and tested, over the `Verdict` of `phil/bot-detection-bundle` |
| `Domain\Collection\ClientDigester` | `digestOf(string $ip): ClientDigest`, a `Pipeline` of `phil/ip-anonymizer` under a `SaltProvider` | Implemented and tested ([rate-limiting.md](rate-limiting.md)) |
| `Domain\Source\ReferralKind` | `Direct`, `Search`, `Social`, `Website`, `Internal`, `Spam`; `isVisit()` | Implemented and tested |
| `Domain\Source\ReferrerClassifier` | `classify(?string $referer, iterable<Origin> $ownOrigins): ReferralKind` over three `HostList` | Implemented and tested |
| `Domain\Device\DeviceClass` | `Desktop`, `Tablet`, `Mobile`; `fromUserAgent()` | Implemented and tested |
| `Domain\Country\GeoDatabaseFreshness` | `maxAgeDays()`, `isUsable(DateTimeImmutable $builtAt, DateTimeImmutable $now)` | Implemented and tested |
| `Domain\Reading\SmallNumberSuppression` | `apply(array<string, int>): SuppressedCounts`, on read | Implemented and tested |
| `Domain\Reading\SuppressedCounts` | `shown`, `others`, `hidden` | Implemented and tested |
| `Domain\Reading\LinkCountReader` | `administrator()`, `author()`, `maySee()`, `counts()` | Implemented and tested ([measures.md](measures.md)) |
| `Domain\Reading\ReaderScope` | `everySite()`, `ofSites()`, `mayRead()`, `coversEverySite()`; `MAX_SITES` = 1000 | Implemented and tested ([sharing-reports-alerts.md](sharing-reports-alerts.md#dashboards-and-several-sites)) |

## Domain — measures (`Domain\Measure`)

All implemented and tested ([measures.md](measures.md)).

| Type | Role |
|---|---|
| `MeasurePlan` | What a site measures beyond page views, its server events included; `of()`; `MAX_SERVER_EVENT_LENGTH` = 50 |
| `MeasureEvent` | The closed list of events; `isClick()` |
| `MeasureNames` | The metrics the bundle writes (`SERVER_EVENTS` among them), `OPEN_DIMENSIONS`, `PRIVATE_METRICS` |
| `ClickTracking` | `Off`, `Aggregate`; `counts()` |
| `ClosedList` | Declared values; `of()`, `empty()`, `downloads()`, `has()`, `toArray()` |
| `DeclaredDimension` | A dimension of the site and its values; `of()` |
| `Goal` | `onRoute()`, `onEvent()`, `isReachedByRoute()`, `isReachedByEvent()` |
| `ConversionRate` | Goals per visit in basis points, or `null` over small numbers; `of()` |
| `RouteTransition` | `valueOf()`: the counter value of a pair of routes |
| `SearchTerm` | `valueOf()`: a term normalized, or `null` |
| `OutboundHost` | `valueOf()`: a host, or `null` |
| `DeclaredOutboundLink` | The reference of a link and its author; `of()` |
| `ScrollDepth`, `ActiveTime` | The bands of reading depth and active time |

## Domain — retention, sharing, reports, alerts

All implemented and tested ([retention.md](retention.md), [sharing-reports-alerts.md](sharing-reports-alerts.md)).

| Type | Role |
|---|---|
| `Domain\Retention\CounterRetention` | `firstDailyDay(UtcDay $today)`; 13 months by default |
| `Domain\Sharing\ShareToken` | `generate()`, `fromString()`, `digest()` |
| `Domain\Sharing\SharedView` | `link()`, `publicBoard()`, `revoked()`, `isOpenAt()`, `mayShow()` |
| `Domain\Report\ReportCadence` | `Weekly`, `Monthly` |
| `Domain\Report\ReportPeriod` | `lastCompleteBefore(UtcDay, ReportCadence)` |
| `Domain\Report\ReportSubscription` | A subscription's id, site, cadence and opaque recipient |
| `Domain\Alert\TrafficAlertRule` | `evaluate(SiteId, UtcDay, int, list<int>): ?TrafficAlert` |
| `Domain\Alert\TrafficAlert`, `TrafficAlertKind` | A day that stands out: `Spike` or `Dip` |

## Application — services

All implemented and tested.

| Type | Role |
|---|---|
| `Application\Measure\MeasureCounters` | `ofPageView()`, `ofEvent()`: the counters a send advances, read from an untrusted body ([measures.md](measures.md)) |
| `Application\Collection\Collector` | `collect(CollectionRequest): CollectionOutcome`, the order of collection; `MAX_BODY_BYTES` = 4096 ([collection-and-admission.md](collection-and-admission.md#the-collection-endpoint)) |
| `Application\Collection\CollectionRequest` | The headers, client address, user agent and body collection reads |
| `Application\Collection\CollectionOutcome` | `Counted`, `NotCounted`, `Refused`, `OverBudget`, `TooLarge`; `httpStatus()` |
| `Application\Retention\CounterRetentionRun` | `runFor(UtcDay)`: the rollup of the days past their retention ([scheduled-jobs.md](scheduled-jobs.md#retention)) |
| `Application\Report\DueReports` | `sendFor(UtcDay)`, `cadencesDueOn(UtcDay)`: the reports due on a day ([scheduled-jobs.md](scheduled-jobs.md#reports)) |
| `Application\Measure\ServerEventCounter` | `count(SiteId, string $event, array $dimensions): bool`: an event the application's server counts ([measures.md](measures.md#events-the-server-counts)) |
| `Application\Reading\CounterReader` | `read()`, `readAcross()`: what a dashboard reads, within a `ReaderScope`; `MAX_DAYS` = 400 ([sharing-reports-alerts.md](sharing-reports-alerts.md#dashboards-and-several-sites)) |
| `Application\Alert\TrafficAlertRun` | `checkFor(UtcDay)`: the visits of the day before against the usual day ([scheduled-jobs.md](scheduled-jobs.md#traffic-alerts)) |
| `Application\Message\ApplyCounterRetention`, `SendDueReports`, `CheckBrokenFlows`, `CheckTrafficAlerts` | The daily messages, without content ([scheduled-jobs.md](scheduled-jobs.md)) |

## Application — contracts (`Application\Contract`)

| Type | Role | State |
|---|---|---|
| `CounterStore` | `increment()`, `counts()`, `deleteSite()` | Port, default `DbalCounterStore` ([extension-points.md](extension-points.md#counterstore)) |
| `CollectionProjection` | `siteDeclaring(Origin): ?CollectionSite`, `site(SiteId): ?CollectionSite` | Port, default `ConfiguredCollectionProjection` ([extension-points.md](extension-points.md#collectionprojection)) |
| `CollectionRateLimiter` | `consume(SiteId, ClientDigest): bool`, fails closed | Port, default `SymfonyCollectionRateLimiter` ([extension-points.md](extension-points.md#collectionratelimiter)) |
| `CountryResolver` | `countryOf(string $ip): CountryCode` | Port, default `UnknownCountryResolver` ([extension-points.md](extension-points.md#countryresolver)) |
| `CrawlerDetector` | `isDeclaredCrawler(string $userAgent): bool` | Port, default `UserAgentCrawlerDetector` ([extension-points.md](extension-points.md#crawlerdetector)) |
| `OutboundLinkDirectory` | `find(SiteId, string): ?DeclaredOutboundLink` | Port, default `NoOutboundLinks` ([extension-points.md](extension-points.md#outboundlinkdirectory)) |
| `CounterRollup` | `rollUpBefore()`, `monthlyCounts()` | Port, default `DbalCounterRollup` ([extension-points.md](extension-points.md#counterrollup)) |
| `SharedViews` | `save()`, `linkOfTokenDigest()`, `publicBoardOf()` | Port only ([extension-points.md](extension-points.md#sharedviews)) |
| `ReportSubscriptions`, `ReportSender`, `TrafficAlertNotifier` | The reports and the alerts | Port only ([extension-points.md](extension-points.md#reportsubscriptions-reportsender-trafficalertnotifier)) |

## Reused from `phil/bot-detection-bundle`

| Type | Role | State |
|---|---|---|
| `Phil\BotDetectionBundle\Domain\Verdict\Verdict` | The browser's `Human`, `Robot` or `Unknown` verdict, read strictly by `fromSubmitted()` | Implemented in the sibling bundle |
| `Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter` | The aggregate robot-verdict counter, `increment()` without argument | Port, for an application that also keeps that aggregate; `Collector` counts robot verdicts per site in the counter store |

## Infrastructure — the default adapters and the wiring

All implemented and tested ([configuration.md](configuration.md#the-adapters)).

| Type | Role |
|---|---|
| `Infrastructure\Doctrine\DbalCounterStore` | `CounterStore` in `phil_analytics_counter`: an `UPDATE`, else an `INSERT`, retried as an `UPDATE` on a concurrent insert; `integers()` |
| `Infrastructure\Doctrine\DbalCounterRollup` | `CounterRollup` into `phil_analytics_monthly_counter`, in one transaction |
| `Infrastructure\Doctrine\CounterTables` | `configure(Schema)`: the two tables; `DAILY`, `MONTHLY` |
| `Infrastructure\Doctrine\CounterSchemaListener` | Adds the two tables to the schema the ORM generates |
| `Infrastructure\Salt\CacheSaltStore` | `ExpiringSaltStore` of `phil/ip-anonymizer` in a PSR-6 pool, under an optional lock |
| `Infrastructure\RateLimiter\SymfonyCollectionRateLimiter` | `CollectionRateLimiter` over a `RateLimiterFactoryInterface`, failing closed |
| `Infrastructure\Collection\ConfiguredCollectionProjection` | `CollectionProjection` of the sites under `phil_analytics.sites` |
| `Infrastructure\Collection\UserAgentCrawlerDetector` | `CrawlerDetector` of the user agents that say so |
| `Infrastructure\Collection\UnknownCountryResolver` | `CountryResolver` that answers unknown |
| `Infrastructure\Collection\NoOutboundLinks` | `OutboundLinkDirectory` without a declared link |
| `Infrastructure\Http\CollectController` | The collection endpoint, route `phil_analytics_collect` |
| `Infrastructure\Messenger\ApplyCounterRetentionHandler`, `SendDueReportsHandler`, `CheckBrokenFlowsHandler`, `CheckTrafficAlertsHandler` | The handlers of the daily messages |
| `Infrastructure\Scheduler\AnalyticsSchedule` | The schedule `phil_analytics`; `NAME` |
| `Infrastructure\BrokenFlow\GoalCompletionHistory` | `CompletionHistory` of `phil/broken-flow-alerts`, from the goal counters; `flows()` |
| `Infrastructure\DependencyInjection\AnalyticsConfiguration`, `AnalyticsServices`, `CounterWiring` | The tree of `phil_analytics` and the services it wires |

## Left to the application

| Piece | Where |
|---|---|
| The dashboard pages, their access rules and their charts | The application's controllers and templates, over `CounterReader` and a `ReaderScope` |
