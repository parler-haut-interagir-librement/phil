# Configuration

```yaml
phil_rate_limit:
    secret: '%kernel.secret%'          # the key of the hashed keys
    headers: draft                     # draft, legacy, both or none
    error_template: '@PhilRateLimit/too_many_requests.html.twig'
    cache_pool: cache.app              # where the limiters keep their state
    lock_factory: null                 # a lock factory, for exact counts under concurrency
    single_server: false               # accepts a store local to the server in prod
    network:                           # needs phil/ip-anonymizer
        enabled: false
        ipv4_prefix: 32
        ipv6_prefix: 64
    limiters:
        api:
            policy: sliding_window     # fixed_window, sliding_window, token_bucket, compound or no_limit
            limit: 100
            interval: '1 minute'
            key: ip                    # ip, user, user_or_ip, ip_route, or a KeyResolver service id
            amount: 1
        reset: { policy: fixed_window, limit: 5, interval: '15 minutes', key: ip_route, lock_factory: lock.factory }
        monthly: { policy: fixed_window, limit: 10000, interval: '1 month', anchor_at: '2026-01-01', key: user }
        contact: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes', amount: 1 } }
        strict: { policy: compound, limiters: [api, reset] }
        shared: { service: limiter.anonymous_api }
        sessions: { policy: sliding_window, limit: 30, interval: '1 minute', storage: app.rate_limit_storage }
        search: { policy: sliding_window, limit: 30, interval: '1 minute', observe: true }
    forms:
        contact: contact
        signup: [{ limiter: api, key: ip_route }, contact]
```

## Options

| Option | Default | Meaning |
|---|---|---|
| `secret` | `%kernel.secret%` | The key of the HMAC-SHA-256 of the `ip`, `user` and `ip_route` keys. |
| `headers` | `draft` | `draft` writes `RateLimit-Policy` and `RateLimit`; `legacy` writes `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset`; `both`; `none` keeps `Retry-After` alone. |
| `error_template` | `@PhilRateLimit/too_many_requests.html.twig` | The page of a refusal answered in HTML. |
| `cache_pool` | `cache.app` | The cache pool of every limiter that names neither a pool nor a storage. |
| `lock_factory` | `null` | The lock factory of every limiter that names none. |
| `single_server` | `false` | Accepts a pool or a storage local to the server in the `prod` environment. |
| `network.enabled` | `false` | The `ip` keys of the client's network rather than its host. |
| `network.ipv4_prefix`, `network.ipv6_prefix` | `32`, `64` | The prefixes kept. |
| `limiters` | `{}` | The limiters, by name: 1 to 64 letters, digits, `_`, `.` and `-`. |
| `forms` | `{}` | The rules of forms by form name. |

## Login forms

A login form is limited with Symfony's `login_throttling`, not with this bundle: it counts each attempt before the
password is checked, so a wrong password and a right one weigh the same, and it groups IPv6 addresses by /64, so one
host cannot spread its attempts over its own network.

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            login_throttling:
                max_attempts: 5
                interval: '15 minutes'
```

The bundle limits everything else: the APIs, the other forms — password reset, contact, sign-up —, and the controllers
and routes declared with `#[RateLimit]` or `_rate_limit`.

## A limiter

| Option | Meaning |
|---|---|
| `policy` | `fixed_window`, `sliding_window`, `token_bucket`, `compound` or `no_limit`; exactly one of `policy` and `service`. |
| `service` | The id of a `RateLimiterFactoryInterface` service, such as a limiter of the `rate_limiter` configuration. |
| `limit` | The hits a window allows, or the size of a bucket; required but for `no_limit`, `compound` and `service`. |
| `interval` | A window's length, as `symfony/rate-limiter` reads it: `'1 minute'`, `'15 minutes'`, `'1 month'`. |
| `rate.interval`, `rate.amount` | A token bucket's refill: `amount` tokens each `interval`. |
| `anchor_at` | A fixed window aligned on the calendar from that moment. |
| `limiters` | The limiters a compound counts, each a policy or a service declared beside it, never a compound. |
| `key` | The key strategy of a rule that names none, `ip` by default. |
| `amount` | What a rule that names none consumes, `1` by default, never more than `limit`. |
| `cache_pool`, `storage`, `lock_factory` | This limiter's own pool, storage service or lock factory. |
| `observe` | `true` counts and reports without refusing: see [Observing a limit](#observing-a-limit). `false` by default. |

Every limiter is built once when the container is built: a missing limit, an interval `symfony/rate-limiter` cannot
read, an anchor on another policy, a compound of nothing or of an unknown limiter fails the build with the path of the
option.

## Observing a limit

A limiter in observation counts every request as any other, in the same store, but never refuses one. When it would
have refused, the request goes on, and the bundle:

- dispatches `Application\Event\RateLimitObserved`, with the limiter, the hashed key, the decision, the route and the
  form, as `RateLimitExceeded` carries them;
- logs a warning, `The rate limit "{limiter}" in observation would have refused a request.`, on the `rate_limit`
  channel of Monolog, with the limiter, the route, the form, the limit and the seconds of the wait — never the key.

Its decision is left out of the response's headers, since the request was not limited. A limit is observed first to
measure it on real traffic, then enforced by removing `observe`:

```yaml
phil_rate_limit:
    limiters:
        search: { policy: sliding_window, limit: 30, interval: '1 minute', observe: true }
```

A limiter of another package or of the application observes with `observe: true` in its tag. Beside an enforced limiter,
an observed one never stops the request; the enforced one still refuses it.

## A store shared by the servers

In the `prod` environment, the build fails on a limiter that counts in a store each server keeps for itself: its pool
ends on the filesystem, APCu, array or system adapter of `symfony/cache`, or its storage is `InMemoryStorage` or a
`CacheStorage` given such a pool. A client could otherwise spend the whole limit on each server. The check is the guard
of `phil/rate-limit`, which the bundle requires. `cache.app` is on the filesystem by default: give it a shared
adapter, or name a pool that has one.

```yaml
phil_rate_limit:
    single_server: true
```

`single_server: true` says the application runs on one server, and turns the check off. A limiter of the `no_limit`
policy counts nothing and is not checked. A factory named by `service` belongs to the application.
