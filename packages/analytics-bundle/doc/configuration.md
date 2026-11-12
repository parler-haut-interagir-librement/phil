# Configuration

The bundle wires its domain services from `phil_analytics`, and one adapter per contract. Each adapter is named by a
keyword for the bundle's own, or by the id of a service of the application that implements the same contract: the
bundle then aliases the contract to that service and registers nothing in its place.

## The whole tree, with its defaults

```yaml
# config/packages/phil_analytics.yaml
phil_analytics:
    small_number_threshold: 5          # at least 2
    single_server: false
    hosts:
        search: ['google.{}', 'bing.com', 'duckduckgo.com', ...]   # see below
        social: ['facebook.com', 'mastodon.social', ...]
        spam: []
    sites: {}                          # the sites of the "config" projection
    salt:
        store: cache                   # or the id of an ExpiringSaltStore
        cache_pool: cache.app
        lock_factory: lock.factory     # null for none
    counters:
        store: dbal                    # or the id of a CounterStore
        rollup: ~                      # the id of a CounterRollup, when the store is not "dbal"
        connection: default
    rate_limiter:
        limiter: symfony               # or the id of a CollectionRateLimiter
        limit: 600
        interval: '10 minutes'
        cache_pool: cache.app
        lock_factory: ~
    collection:
        enabled: true
        projection: config             # or the id of a CollectionProjection
        crawler_detector: user_agent   # or the id of a CrawlerDetector
        country_resolver: unknown      # or the id of a CountryResolver
        outbound_links: none           # or the id of an OutboundLinkDirectory
    retention:
        enabled: true
        months: 13
    reports:
        enabled: false
        subscriptions: ~               # the id of a ReportSubscriptions
        sender: ~                      # the id of a ReportSender
    broken_flows:
        enabled: false
        flows: {}
        baseline_days: 28
        window_days: 7
        significance: 0.001
        weekly: true
        sender: ~                      # the id of an AlertSender; null, through the notifier
    traffic_alerts:
        enabled: false
        sites: []                      # the references of the watched sites
        notifier: ~                    # the id of a TrafficAlertNotifier
        spike_percent: 300
        dip_percent: 30
        minimum_baseline: 20
        baseline_days: 28              # 7 to 366
    schedule:
        enabled: true
        time: '05:00'                  # UTC
```

`bin/console config:dump-reference phil_analytics` prints the same tree with the help of each key.

## Domain settings

| Key | Default | Becomes |
|---|---|---|
| `small_number_threshold` | `5` | `SmallNumberThreshold`, read by `SmallNumberSuppression` and by the rollup of open dimensions. Under 2 fails the build. |
| `single_server` | `false` | In the `prod` environment, the build refuses a `salt.cache_pool` or a `rate_limiter.cache_pool` local to each server — the filesystem `cache.app` among them —, where each server would draw its own salt and a client could spend its budget once per server; `true` accepts it, for an application on one server. |
| `hosts.search` | Ten search engines: `google.{}`, `bing.com`, `duckduckgo.com`, `search.yahoo.com`, `yandex.{}`, `baidu.com`, `ecosia.org`, `qwant.com`, `search.brave.com`, `startpage.com` | The search `HostList` of `ReferrerClassifier` |
| `hosts.social` | Twelve networks: `facebook.com`, `instagram.com`, `linkedin.com`, `reddit.com`, `x.com`, `twitter.com`, `t.co`, `youtube.com`, `pinterest.com`, `threads.net`, `bsky.app`, `mastodon.social` | The social `HostList` |
| `hosts.spam` | none | The spam `HostList`, for instance loaded from a CC0 referrer-spam list |

Giving a list replaces its default rather than adding to it. A host the value object refuses surfaces as an
`InvalidValue` when `ReferrerClassifier` is first built, not when the container is compiled.

## The sites: `sites`

The `config` projection declares the sites known when the application is deployed:

```yaml
phil_analytics:
    sites:
        spring-con:
            origins: ['https://example.com', 'https://www.example.com']
            routes:
                home: '/'
                talk: '/talks/{slug}'
                thanks: '/tickets/thanks'
            status: active                 # active, suspended or retired
            goals:
                ticket: { route: thanks }
                contact: { event: form_submit, value: contact }
            forms: [contact]
            click_names: [cta-buy]
            clicks: aggregate              # or off
            downloads: ~                   # null for the default extensions, or a list such as [pdf, zip]
            dimensions:
                checkout_button: [a, b]    # the variant of an experiment, a plan, a language
            server_events: [signup, ticket_sold]
```

The key of each site is its `SiteId`, kept as written (dashes included). Each site needs at least one origin, and an
origin belongs to one site only: two sites that declare it are refused when the projection is first built. A goal
names a declared route, or a measured event and its value, never both. An application whose sites change at run time
names its own `CollectionProjection` under `collection.projection` instead.

| Key | Default | Becomes |
|---|---|---|
| `clicks` | `aggregate` | `ClickTracking`: a counter per declared click name, tied to nobody; `off` measures no click, download or outbound link |
| `downloads` | `null`, the default extensions of `ClosedList::downloads()` | The extensions counted as downloads |
| `dimensions` | none | One `DeclaredDimension` per name, each with its closed list of values |
| `server_events` | none | The events `ServerEventCounter` counts for the site ([measures.md](measures.md#events-the-server-counts)) |

## The adapters

| Key | Bundle's adapter | Contract |
|---|---|---|
| `salt.store: cache` | `CacheSaltStore`, under `ForgottenSalt` (a new random salt each UTC day) | `Phil\IpAnonymizer\Contract\ExpiringSaltStore` |
| `counters.store: dbal` | `DbalCounterStore` and `DbalCounterRollup` | `CounterStore`, `CounterRollup` |
| `rate_limiter.limiter: symfony` | `SymfonyCollectionRateLimiter`, a sliding window of Symfony Rate Limiter | `CollectionRateLimiter` |
| `collection.projection: config` | `ConfiguredCollectionProjection`, the sites above | `CollectionProjection` |
| `collection.crawler_detector: user_agent` | `UserAgentCrawlerDetector` | `CrawlerDetector` |
| `collection.country_resolver: unknown` | `UnknownCountryResolver`: every country unknown | `CountryResolver` |
| `collection.outbound_links: none` | `NoOutboundLinks`: outbound clicks counted by host only | `OutboundLinkDirectory` |

Every contract is also an alias of the container, public, so the application can fetch it or redefine it in its own
`services.yaml`, which is read after the bundle's configuration.

### The salt

`CacheSaltStore` keeps each day's salt in `salt.cache_pool` until the end of the day, then the pool forgets it. The pool
must be **shared by every server** of collection, or each draws its own salt and the limiter's budget is multiplied by
the number of servers; and it must **not be persisted**, or the salts it was meant to forget link two days of digests
again. A Redis or Memcached pool without persistence fits both; the default `cache.app` fits a single server whose
filesystem cache is not backed up. With a lock factory shared by the servers, the first claim of a day is atomic between
them. The service `phil_analytics.salt_provider` is the `SaltProvider` of `ClientDigester`.

### The counters

`DbalCounterStore` and `DbalCounterRollup` use the Doctrine connection `counters.connection`, in two tables,
`phil_analytics_counter` and `phil_analytics_monthly_counter`. With DoctrineBundle and the ORM, the tables join the
schema the ORM generates, so `doctrine:migrations:diff` writes the migration that creates them.

**Without DoctrineBundle**, the `dbal` store has no connection: the bundle boots, and the collection endpoint and the
jobs are not wired until the application names a `CounterStore` under `counters.store` (and a `CounterRollup` under
`counters.rollup`, for the retention).

### The rate limiter

A sliding window of `limit` sends per `interval`, per site and keyed client digest, with its state in
`rate_limiter.cache_pool`. Calibrate the limit on a group behind one address (a conference Wi-Fi, a carrier-grade NAT),
counting several sends per page: the page view, the second send when the page is left, one per event. The limiter fails
closed: an error of the pool refuses the page view ([rate-limiting.md](rate-limiting.md)).

## The collection endpoint

With `collection.enabled` and a counter store, the bundle registers `Collector` and `CollectController`. Import the route:

```yaml
# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

The route `phil_analytics_collect` answers `POST /analytics/collect` without a body
([collection-and-admission.md](collection-and-admission.md#the-collection-endpoint)).

## The daily jobs

`retention`, `reports`, `broken_flows` and `traffic_alerts` are jobs run once a day on the schedule `phil_analytics`, at `schedule.time`
in UTC ([scheduled-jobs.md](scheduled-jobs.md)):

| Key | Wired when | Requires |
|---|---|---|
| `retention` | enabled (the default) and the store has a rollup | — |
| `reports` | enabled | `reports.subscriptions` and `reports.sender`; enabling without both fails the build |
| `broken_flows` | enabled | `phil/broken-flow-alerts`; the `notifier` service, unless `broken_flows.sender` names an `AlertSender` |
| `traffic_alerts` | enabled | `traffic_alerts.notifier` and at least one site under `traffic_alerts.sites`; enabling without both fails the build |
| `schedule` | enabled (the default) and `symfony/scheduler` is installed | `symfony/messenger`, to consume `scheduler_phil_analytics` |

## Build-time errors

| Configuration | Error |
|---|---|
| A key the tree does not declare | `Unrecognized option "foo" under "phil_analytics".` |
| `small_number_threshold: 1` | `The value 1 is too small for path "phil_analytics.small_number_threshold".` |
| A site without origins | `The path "phil_analytics.sites.<site>.origins" should have at least 1 element(s) defined.` |
| A goal with both a route and an event, or an event without a value | `A goal names a declared route, or a measured event and its value` |
| `reports.enabled` without `subscriptions` or `sender` | `Reports need the ids of the ReportSubscriptions and ReportSender services` |
| `schedule.time` that is not `HH:MM` | `The time of the jobs is HH:MM, in UTC` |
| In `prod`, a salt or limiter pool local to each server, without `single_server` | `phil_analytics.rate_limiter.cache_pool: the store "cache.app" is local to each server, so a client could spend the limit once per server. …` |
| `broken_flows.significance` at or under 0, or at or above 0.5 | `The significance of a broken flow is above 0 and under 0.5` |
| `broken_flows.enabled` without `phil/broken-flow-alerts` | `phil_analytics.broken_flows needs phil/broken-flow-alerts` |
| `traffic_alerts.enabled` without `notifier` or `sites` | `Traffic alerts need the id of a TrafficAlertNotifier service and at least one watched site` |
| A server event that does not start with a letter, or longer than 50 characters | `"<event>" cannot be a server event`, when the projection is first built |
