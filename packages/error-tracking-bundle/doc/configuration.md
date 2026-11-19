# Configuration

Every key of `phil_error_tracking`, with its default.

```yaml
phil_error_tracking:
    release: ~                         # the version or commit that runs; regressions are told by it
    environment: '%kernel.environment%'
    project_dir: '%kernel.project_dir%' # its frames outside vendor/ are the application's
    sample_rate: 1.0                   # the share of the events kept, 0.0 to 1.0
    max_frames: 50                     # frames per exception, 1 to 500
    max_chain: 10                      # exceptions per chain, 1 to 50
    scrub_fields: []                   # parts of a name that make a header or a tag sensitive
    token_path_prefixes: []            # /reset-password/reset/: the next segment is a token, filtered
    ignored_exceptions: []             # classes never captured, with their subclasses
    client_address:
        ipv4_prefix: 24
        ipv6_prefix: 48
    rate_limit:                        # events kept per group; false turns it off
        limit: 30
        interval: '1 minute'
        cache_pool: cache.app          # a shared pool in prod
        single_server: false           # accepts a pool local to the server in prod
    capture:
        http:                          # false turns it off
            client_errors: false       # the HTTP exceptions below 500 too
        messenger:                     # false turns it off
            retries: false             # each failed attempt, not only the last one
        console: true
        monolog_level: error           # the level from which the handler captures
    storage:
        connection: default
        events_per_group: 50           # the latest events kept per group, 1 to 10000
        retention: 30                  # days, for phil:error-tracking:purge
    alerts:                            # false turns them off
        transport: ~                   # the chatter transport; its default one when null
        async: true                    # the chat message through Messenger rather than in the request
        recipients: []                 # identifiers notified by phil/notifications-bundle
        audience: ~                    # or an audience of that bundle
    health_check:                      # with phil/monitoring-bundle; false turns it off
        new_groups: 20                 # new groups within the window that make it degraded; 0 ignores them; at most 200
        window: 900                    # seconds, up to a day
    admin:                             # off unless enabled
        enabled: false
        role: ROLE_ADMIN
        layout: '@PhilErrorTracking/layout.html.twig'
        page_size: 50
        events_shown: 10
    browser:                           # off unless enabled
        enabled: false
        max_bytes: 16384               # 512 to 262144
        max_frames: 50
        origins: []                    # https://example.com; the endpoint's own when empty
        token: ~                       # checked in X-Error-Tracking-Token when set
        rate_limit:                    # reports per client; false turns it off
            limit: 10
            interval: '1 minute'
            cache_pool: cache.app      # a shared pool in prod
            single_server: false       # accepts a pool local to the server in prod
    clock: clock                       # the service id of the PSR-20 clock
```

## What fails the build

| Value | Message |
|---|---|
| an origin that is not `scheme://host[:port]` | `phil_error_tracking.browser.origins: "…" is not an origin such as https://example.com.` |
| an ignored class that is not a throwable | `phil_error_tracking.ignored_exceptions: "…" is not a throwable class.` |
| a token path prefix that is not one or more path segments between slashes | `phil_error_tracking.token_path_prefixes: "…" is not a token path prefix: …` |
| an empty or non-text clock or project directory | `phil_error_tracking: clock must be a string.` |
| a sample rate, a bound or a prefix outside its range | the message of the Config component |
| a rate limit enabled without `symfony/rate-limiter` | `… needs symfony/rate-limiter: install it, or set "enabled" to false.` |
| in the `prod` environment, a `cache_pool` on the filesystem, APCu, array or system adapter, as `cache.app` is by default, unless `single_server: true` says the application runs on one server | `…: the store "…" is local to each server…` |
| both `alerts.recipients` and `alerts.audience`, or either without PhilNotificationsBundle, or a recipient that is not an identifier | `phil_error_tracking.alerts: …` |

## Services that need their bundle

The administration pages need SecurityBundle, its CSRF protection and TwigBundle. A chat alert needs a chatter transport
in the notifier's configuration — without one, the alerts are in the degraded state of [Alerts](alerts.md) and the build
goes on — and is sent in the request without a Messenger bus. The rate limits count in a pool
shared by the servers:

```yaml
framework:
    cache:
        pools:
            cache.rate_limit: { adapter: cache.adapter.redis }

phil_error_tracking:
    rate_limit: { cache_pool: cache.rate_limit }
    browser: { rate_limit: { cache_pool: cache.rate_limit } }
```
