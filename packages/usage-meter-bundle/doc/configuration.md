# Configuration

```yaml
phil_usage_meter:
    store: dbal                     # dbal, memory, or the id of a UsageStore service
    dbal:
        connection: default
        counter_table: phil_usage_counter
        key_table: phil_usage_key
    clock: clock
    limit_providers: [App\Usage\AdminOverrides, App\Usage\PlanLimits]
    subscription_features:
        enabled: false
        prefix: 'usage:'
    anchor_provider: App\Usage\SubscriptionAnchors
    meters:
        exports: { limit: 3, calendar: month, timezone: America/Toronto }
        requests: { limit: 600, rolling: 3600, buckets: 60 }
        ai.tokens: { limit: 50000, billing: '2026-01-01T00:00:00-05:00', months: 1 }
        projects: { limit: unlimited, calendar: year }
    rate_limiters:
        api: { meter: requests, key: ip, amount: 1, idempotency_key: false }
    idempotency_secret: '%kernel.secret%'
```

Every key is optional; with none, the bundle wires a `UsageMeter` without meters on the DBAL store.

## The store

| Key | Default | Meaning |
|---|---|---|
| `store` | `dbal` | `dbal`: `Phil\UsageMeter\Dbal\DbalUsageStore` on a DBAL connection; `memory`: `Phil\UsageMeter\InMemoryUsageStore`, the counters of one process; any other value: the id of a service implementing `Phil\UsageMeter\UsageStore` |
| `dbal.connection` | `default` | The DoctrineBundle connection of the tables, `doctrine.dbal.<name>_connection` |
| `dbal.counter_table` | `phil_usage_counter` | The table of the counters: up to 63 lowercase letters, digits or `_` |
| `dbal.key_table` | `phil_usage_key` | The table of the idempotency keys |

`Phil\UsageMeter\UsageStore` is an alias of the store chosen, for the services that need it. With `dbal` and
`doctrine/orm`, `UsageSchemaListener` adds both tables to the schema the ORM generates for that connection
([Installation](installation.md)). `store: dbal` without `doctrine/dbal` fails the container build.

## The meters

Each entry of `meters` declares one meter, by name — up to 64 lowercase letters, digits, `_`, `.` or `-`, starting with
a letter or a digit —, with its limit and exactly one period:

| Key | Default | Meaning |
|---|---|---|
| `limit` | required | The limit of a subject no limit provider names: `unlimited`, or a whole number from zero |
| `calendar` | none | `day`, `week` (ISO, from Monday), `month` or `year`, from local midnight in `timezone` |
| `timezone` | `UTC` | The time zone of a calendar period |
| `rolling` | none | A rolling window of so many seconds |
| `buckets` | `1` | The buckets of the rolling window, 1 to 1440, dividing it exactly |
| `billing` | none | Billing periods from this anchor, a date and time with its offset; an unquoted YAML date is read as midnight UTC |
| `months` | `1` | The length of a billing period, 1 to 120 months |

The bundle builds every meter while the container is built: a meter with no period or two, a limit that is not one, an
unknown time zone, buckets that do not divide the window, or an anchor that is not a date fails the build with a
message naming the meter. The documentation of `phil/usage-meter`, under *Meters and periods*, says how each period
cuts time.

## The limits

| Key | Default | Meaning |
|---|---|---|
| `limit_providers` | `[]` | Service ids of `Phil\UsageMeter\LimitProvider`s, asked in order; the first that names a limit wins |
| `subscription_features.enabled` | `false` | Whether the features of a subscriber's offers name limits, with `phil/commerce-subscription-bundle` |
| `subscription_features.prefix` | `usage:` | What starts such a feature: `usage:exports:20`, `usage:projects:unlimited` |

The providers, then the subscription features, are put in a `Phil\UsageMeter\LimitChain`, the service
`phil_usage_meter.limits`; a subject none of them names a limit for gets the meter's own. With neither, `UsageMeter`
reads the meters' limits alone.

## The anchors and the clock

| Key | Default | Meaning |
|---|---|---|
| `anchor_provider` | none | Service id of a `Phil\UsageMeter\AnchorProvider`: each subject's own anchor for the meters counted by billing cycle |
| `clock` | `clock` | Service id of the PSR-20 clock the windows are read from |

## The rate limiters

| Key | Default | Meaning |
|---|---|---|
| `rate_limiters.<name>.meter` | required | The meter the limiter counts on, declared in `meters` |
| `rate_limiters.<name>.key` | `ip` | Who a request counts for when a rule names no key: a key strategy of `phil/rate-limit-bundle` — `ip`, `user`, `user_or_ip`, `ip_route` — or the id of a service implementing its `KeyResolver` |
| `rate_limiters.<name>.amount` | `1` | What one request counts when a rule names no amount, one or more |
| `rate_limiters.<name>.idempotency_key` | `false` | Whether a POST, PUT, PATCH or DELETE request is counted once per `Idempotency-Key` header; see [rate-limiting.md](rate-limiting.md) |
| `idempotency_secret` | `%kernel.secret%` | The key of the hashes the idempotency keys are kept as |

A limiter on a meter the `meters` section does not declare fails the container build, and so do limiters without
`PhilRateLimitBundle` registered. The headers, the keys' secret and the answer are `phil/rate-limit-bundle`'s
configuration. [Rate limiting](rate-limiting.md)
says how they count.
