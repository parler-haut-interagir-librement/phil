# Testing

## How the bundle is tested

Every implemented type has a PHPUnit 13 unit test under `tests/Unit/`, mirroring `src/`:

| Test | Covers |
|---|---|
| `tests/Unit/PhilAnalyticsBundleTest.php` | The extension alias is `phil_analytics`; without DoctrineBundle, the `dbal` store has no connection |
| `tests/Unit/Infrastructure/DependencyInjection/AnalyticsServicesTest.php` | What each block of `phil_analytics` wires: the default adapters, the application's services in their place, the jobs, and every refused configuration |
| `tests/Unit/Domain/ValueObject/*Test.php` | Each value object: accepted and refused inputs, normalization, equality |
| `tests/Unit/Domain/Collection/AdmissionRuleTest.php` | The fixed admission order, including refusal before anything else and the strict verdict |
| `tests/Unit/Domain/Source/ReferrerClassifierTest.php` | The six source classes and `isVisit()` |
| `tests/Unit/Domain/Device/DeviceClassTest.php` | User agents per class, including the `Tablet PC` and lower-case cases |
| `tests/Unit/Domain/Country/GeoDatabaseFreshnessTest.php` | The age boundary, and a build date in the future |
| `tests/Unit/Domain/Reading/SmallNumberSuppressionTest.php` | Shown, grouped and hidden counters, the stored `(other)` counter |
| `tests/Unit/Domain/Reading/LinkCountReaderTest.php` | Who reads each link's count |
| `tests/Unit/Domain/Measure/*Test.php` | The closed lists, the goals, the pairs of routes, the terms and hosts refused |
| `tests/Unit/Application/Measure/MeasureCountersTest.php` | The counters of each event; junk in every field counts nothing |
| `tests/Unit/Domain/{Retention,Sharing,Report,Alert}/*Test.php` | The retention bound, tokens and revocation, report periods, spikes and dips |
| `tests/Unit/Application/Collection/CollectorTest.php` | The order of collection: refusals before the limiter, the outcomes, junk bodies, the counters a page view, an event and a robot verdict advance |
| `tests/Unit/Application/{Report,Retention}/*Test.php` | The reports due on a day, the bound of the rollup |
| `tests/Unit/Infrastructure/Doctrine/DbalCountersTest.php` | Increments, reads, the retirement of a site and the rollup, on SQLite in memory |
| `tests/Unit/Infrastructure/{Salt,RateLimiter,Collection,BrokenFlow,Scheduler,Messenger}/*Test.php` | The salt claimed once and forgotten, the limiter failing closed, the default adapters, the goal history, the schedule and the handlers |

Each test class declares `#[CoversClass]` for the types it covers. The unit tests use no container and no network;
instants come from a `MockClock`, and the DBAL adapters run on SQLite in memory. Ports have no test of their own: they
are interfaces.

A functional suite under `tests/Functional/` boots a real kernel, `tests/Functional/App/TestKernel.php`
(FrameworkBundle, DoctrineBundle on a SQLite file, and the bundle under the `phil_analytics` block of each test), with
its cache in a directory of its own under the system temporary directory. `BundleRegistrationTest` pins the alias, the
reference `config:dump-reference phil_analytics` prints, the build failure an unknown key causes, and a kernel without
DoctrineBundle that boots without counting; `ApplicationWiringTest` uses the domain services the bundle wires from its
configuration; `CollectionTest` starts the bundle with its default adapters and a declared site, sends page views to
the route `phil_analytics_collect`, and reads the counters back. It needs no server and no environment variable:
`vendor/bin/phpunit packages/analytics-bundle/tests/Functional`.

Tests use `Phil\BotDetectionBundle\Domain\Verdict\Verdict` and `Phil\DddBundle\Domain\Exception\InvalidValue`
from the sibling bundles, which are therefore needed to run them.

## Running the tests

### From the monorepo root

The bundle's tests run on the root `vendor/` and the root PHPUnit configuration:

```bash
vendor/bin/phpunit packages/analytics-bundle/tests
```

The collection script runs under Node's test runner, Stimulus resolved to a stub by `tests/js/register.mjs`:

```bash
node --import ./packages/analytics-bundle/tests/js/register.mjs --test packages/analytics-bundle/tests/js
```

### The bundle installed alone

This is what a project installing the bundle sees, and it catches a dependency the bundle uses without
declaring. The bundle has no `phpunit.xml` of its own, so run PHPUnit without configuration:

```bash
cp -R packages/analytics-bundle /tmp/analytics-bundle && cd /tmp/analytics-bundle
rm -rf vendor composer.lock
# phil/ddd-bundle and phil/bot-detection-bundle are not on Packagist yet: point Composer at the monorepo
composer config repositories.siblings '{"type": "path", "url": "/path/to/monorepo/packages/*", "options": {"symlink": false}}'
composer install
vendor/bin/phpunit --no-configuration tests
```

The monorepo runs exactly this for every bundle with `composer packages`, which also
runs `composer-dependency-analyser` with the bundle's own `composer-dependency-analyser.php`. That file declares
the optional integrations — Doctrine, Symfony Lock, Symfony Scheduler, `phil/broken-flow-alerts` —, required in
development only and suggested.

### Quality gates

In the monorepo, the bundle's code is also under the repository's static analysis, architecture rules, coding
standard and mutation testing. None of them needs a configuration inside the bundle.

## Testing your adapters

The ports are small enough that in-memory adapters are a few lines each. Use them to test your collection
endpoint and dashboards without a database:

```php
final class InMemoryCounterStore implements CounterStore
{
    /** @var array<string, int> */
    public array $counters = [];

    public function increment(CounterKey $key): void
    {
        $id = implode('|', [$key->site, $key->day, $key->metric, $key->dimension, $key->value]);
        $this->counters[$id] = ($this->counters[$id] ?? 0) + 1;
    }

    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
    {
        $out = [];
        foreach ($this->counters as $id => $n) {
            [$s, $d, $m, $dim, $value] = explode('|', $id, 5);
            if ($s === $site->value && $m === $metric && $dim === $dimension
                && !UtcDay::fromString($d)->isBefore($from) && !$to->isBefore(UtcDay::fromString($d))) {
                $out[$value] = ($out[$value] ?? 0) + $n;
            }
        }

        return $out;
    }

    public function deleteSite(SiteId $site): void
    {
        $this->counters = array_filter($this->counters, static fn (string $id): bool => !str_starts_with($id, $site->value.'|'), \ARRAY_FILTER_USE_KEY);
    }
}

final class FailingRateLimiter implements CollectionRateLimiter
{
    public function consume(SiteId $site, ClientDigest $digest): bool
    {
        return false; // what a fail-closed limiter returns when its backend is down
    }
}
```

What to assert on your real adapters, per port:

| Port | Assert |
|---|---|
| `CounterStore` | Two concurrent `increment()` of the same key give 2 (run against the real database); `counts()` includes both ends of the range and sums days; `deleteSite()` leaves other sites untouched; nothing but the key parts is stored |
| `CollectionProjection` | An undeclared origin gives `null`; every declared origin of the site is returned; a suspended site is returned with its status (collection refuses it, not the projection) |
| `CollectionRateLimiter` | The budget is enforced per site and per digest; **a backend failure returns `false` or throws**; the stored key is `ClientDigest::toString()`, never an address; entries expire |
| `OutboundLinkDirectory` | An unknown reference gives `null` |
| `CounterRollup` | Two runs give the totals of one; a failure leaves days and months as they were; a rare term is gone |
| `SharedViews` | No column holds the token; a lookup by digest finds a revoked view with its `revokedAt` |
| `CountryResolver` | An address it cannot place, or a private one, gives `unknown()`; a database older than the freshness bound gives `unknown()`; the address appears in no log |
| `CrawlerDetector` | A declared crawler's user agent is detected; an ordinary browser's is not |
| `RobotVerdictCounter` | One adapter instance per site; `increment()` changes only that site's count |

`CollectorTest` checks that the bundle's endpoint never writes for a suspended site and never consumes the limiter for
it. An endpoint of the application's own is worth the same test, with a spy limiter.

Installed on its own, the functional suite is skipped: with `symfony/event-dispatcher` required for development
only, Symfony's container leaves the event dispatcher out and no kernel boots. It runs from an application that
requires an HTTP kernel, such as the monorepo root.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/analytics-bundle/tests/Benchmark
```
