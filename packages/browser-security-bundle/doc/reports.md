# Reports

A browser that meets a violation posts a report to the address the policy names. The bundle names its own endpoint,
`phil_csp_report`, in both reporting directives and in the `Reporting-Endpoints` header:

```http
Content-Security-Policy: default-src 'self'; report-uri https://www.example.com/_csp/report; report-to csp
Reporting-Endpoints: csp="https://www.example.com/_csp/report"
```

`report-uri` serves the browsers that only know it; `report-to` and `Reporting-Endpoints` serve the Reporting API. The
address is absolute, generated from the route; `reporting.uri` names another one — a path of the site or an `https`
address. A policy with `report: false` carries neither directive.

## The two formats

| Media type | Body | Read as |
|---|---|---|
| `application/csp-report` | `{"csp-report": {"document-uri": …, "effective-directive": …}}` | one report |
| `application/reports+json` | `[{"type": "csp-violation", "body": {"documentURL": …, "effectiveDirective": …}}, …]` | the reports of type `csp-violation`; the others are ignored |
| `application/json` | either shape | as its shape says |

From each report the bundle keeps the page, what was blocked, the effective directive (or the first word of the
violated directive), the disposition, the source file, line and column, the status code and the sample. It drops the
referrer, the original policy and the user agent. A report without a page or a valid directive is dropped.

## The answer

| Status | When |
|---|---|
| `204` | The body was read; its reports are kept. |
| `400` | The body is not JSON within eight levels, or not in the shape of its media type. |
| `413` | The body is larger than `max_bytes`. |
| `415` | The media type is none of the three. |
| `429` | The client posted more bodies than the throttle allows. |

The response is empty and `Cache-Control: no-store`. The checks run in that order: a body of the wrong type or too
large never counts against the throttle.

## Bounds

| Bound | Value |
|---|---|
| A body | `max_bytes`, 64 KiB by default; the endpoint reads one byte more and stops |
| The depth of the JSON | 8 levels |
| The reports of a body | `max_reports`, 20 by default; the rest is ignored |
| An address | 2,048 bytes, cut on a character |
| A sample | 40 characters |
| A number | 0 to 1,000,000,000 |

## Privacy

- An address loses its credentials, its query and its fragment, where tokens and personal data hide:
  `https://user:secret@example.com/reset?token=abc#x` is kept as `https://example.com/reset`.
- Control characters are removed from every text.
- The client's address is never kept raw. The throttle counts an HMAC-SHA-256 of it, keyed by the kernel secret. The
  store and the event get it truncated by `phil/ip-anonymizer` — to a /24 in IPv4 and a /48 in IPv6 by default —, or
  nothing when that package is missing or `client_address` is off.

## The throttle

Each client may post `limit` bodies per `interval` — 60 a minute by default —, counted in a sliding window kept in
`cache_pool`. The key is always the keyed digest of the address. `symfony/rate-limiter` is needed: with `rate_limit`
enabled, the container does not build without it; set `rate_limit: false` to go without a throttle.

In the `prod` environment, the build also refuses a `cache_pool` local to each server, on the filesystem, APCu, array
or system adapter, which is what `cache.app` is by default: name a pool on Redis, Memcached or a database. The check is
the guard of `phil/rate-limit`, shared with the other bundles that count in a pool.

```yaml
framework:
    cache:
        pools:
            cache.rate_limit: { adapter: cache.adapter.redis }

phil_browser_security:
    reporting:
        rate_limit: { cache_pool: cache.rate_limit }
```

When phil/rate-limit-bundle is registered, the bodies are counted by its `Limiter` contract, through a limiter named
`phil_browser_security.report` over the same window: it is listed with the others of the application, and decorating
the service `phil_browser_security.report_limit` replaces it. Declaring another limiter of that name fails the build.
Without that bundle, the bundle's `RateLimiterThrottle` counts on `symfony/rate-limiter` directly.

`limiter` names a rate limiter factory of the application instead of the window built here; its storage is then the
application's:

```yaml
framework:
    rate_limiter:
        csp_reports: { policy: token_bucket, limit: 100, rate: { interval: '1 minute', amount: 20 } }

phil_browser_security:
    reporting:
        rate_limit: { limiter: limiter.csp_reports }
```

## Deduplication and the event

Each report of a body is kept once per fingerprint — the page, what was blocked, the directive, the place in the code
and the disposition. A report then dispatches `ViolationReported`, unless the same fingerprint was announced within the
window of deduplication, an hour by default, kept in a PSR-6 pool shared by the workers:

```php
use Phil\BrowserSecurityBundle\Application\Event\ViolationReported;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AlertOnViolation
{
    public function __invoke(ViolationReported $event): void
    {
        // $event->report->documentUri, $event->report->effectiveDirective, $event->client
    }
}
```

## The store

```yaml
phil_browser_security:
    reporting:
        storage: { enabled: true, connection: default, capacity: 10000, retention: 30 }
```

Every report adds one to the row of its fingerprint in `phil_csp_report`, in one statement, or creates the row; two
first reports at once end in the same row. Beyond `capacity` rows, a new violation is not kept, while the known ones
keep counting. `ReportRepository::latest()` reads the rows last seen first.

```sh
bin/console phil:csp:purge-reports --older-than=30
```

removes the violations not reported for that many days — the `retention` by default —, for the scheduler to run
every night.
