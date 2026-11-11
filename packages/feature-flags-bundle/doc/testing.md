# Testing

## The bundle's own suites

| Suite | Directory | Boots a kernel | What it covers |
|---|---|---|---|
| Unit | `tests/Unit` | no | The domain (names, buckets with pinned values, rules, experiments, metadata, requests), the application services (evaluator, catalog, toggler, editor, the override that forces a circuit open), the configuration readers, the compiler pass of the force-open flags, and the bundle's wiring on a `ContainerBuilder` (`PhilFeatureFlagsBundleTest`), alone and with `phil/circuit-breaker-bundle`. |
| Functional | `tests/Functional` | yes, `tests/Functional/App/TestKernel` | The admin, the database store on a real connection (conditional writes, per-request reading, unreadable rows reported and evaluated as `PARSE_ERROR`, schema generation), the controller attribute, the route condition, the Twig functions, exposures, and the listing command. |

Unit tests use in-memory fixtures from `tests/Unit/Fixture` (`InMemoryFlagStore`, `InMemoryDefinitions`,
`InMemoryCircuitStore`, `ListAuthorizer`, `RecordingAuditor`, `RecordingExposures`, `FrozenClock`,
`CountingProvider`, `CountingResolver`, `SwitchableResolver`, `CallJournal`), which are also good models for an
application's own test doubles.

### The test kernel

`TestKernel` is the smallest application the bundle needs: FrameworkBundle, DoctrineBundle, TwigBundle and the
bundle, with the database store, the admin under `/admin/flags`, a few controllers behind flags, three configured
flags (`new-checkout`, `checkout-layout`, `forgotten` expired in 2020), the tenant from the `X-Tenant` header and the
operator from `X-Operator`; only the operator `operator` may change a flag. `FunctionalTestCase` drops and recreates
the two flag tables before each test and sends each request through the kernel as a long-running worker would
serve it, so services are reset between two requests.

### The database

By default the functional suite runs on a SQLite file in the kernel's cache directory (under the system temp
directory). Set `PHIL_FEATURE_FLAGS_TEST_DATABASE_URL` to run it on another server:

```bash
PHIL_FEATURE_FLAGS_TEST_DATABASE_URL='mysql://root:secret@127.0.0.1:3306/flags' vendor/bin/phpunit packages/feature-flags-bundle/tests/Functional
PHIL_FEATURE_FLAGS_TEST_DATABASE_URL='postgresql://app:secret@127.0.0.1:5432/app_test?serverVersion=17' vendor/bin/phpunit packages/feature-flags-bundle/tests/Functional
```

The compiled container is cached per database URL. The tests drop and create `phil_feature_flags_definition` and
`phil_feature_flags_state` in that database: point it at a disposable one.

## Running from the monorepo root

The bundle is incubated in a monorepo whose root `vendor/` and `phpunit.dist.xml` run its tests:

```bash
vendor/bin/phpunit packages/feature-flags-bundle/tests            # both suites
vendor/bin/phpunit packages/feature-flags-bundle/tests/Unit
composer test:platforms                                           # the functional suite on MySQL 8 and PostgreSQL
```

The root configuration puts `tests/Unit` in its `Unit` suite and `tests/Functional` in its `Integration` suite.
`composer test:platforms` starts a throw-away MySQL 8 container (`mysql:8.4` by default, `MYSQL_IMAGE` overrides it,
on a random local port, removed on exit) and uses the PostgreSQL server of the local stack (database `app_test`,
`serverVersion=17` added when the URL has none), then runs `tests/Functional` once on each by setting
`PHIL_FEATURE_FLAGS_TEST_DATABASE_URL`. It needs Docker and the PostgreSQL service running; this is the run that
proves the booleans and the JSON column behave the same on both servers.

`composer packages` installs each bundle alone in a fresh directory, runs `composer-dependency-analyser` with the
bundle's `composer-dependency-analyser.php` (every declared package must be imported, every imported package
declared), then the bundle's tests with `vendor/bin/phpunit --no-configuration tests`.

## Running the bundle installed alone

```bash
cd packages/feature-flags-bundle      # or a checkout of the bundle
composer install                      # phil/ddd-bundle must be reachable through a path or vcs repository
vendor/bin/phpunit --no-configuration tests
```

The development dependencies (`doctrine/doctrine-bundle`, `phpunit/phpunit`, `psr/log`, `symfony/clock`,
`symfony/expression-language`, `symfony/framework-bundle`, `symfony/twig-bundle`) are what the test kernel needs.

## Mutation testing

The domain (`src/Domain`) is held to a 100 % mutation score: Infection runs the `Unit` suite against every
`Domain` directory of the monorepo's bundles (`Application` and `Infrastructure` are excluded), with `minMsi` and
`minCoveredMsi` at 100. From the root:

```bash
composer infection
```

A surviving mutant in `Domain` means a missing test or unreachable code. The pinned bucket values in `BucketTest`
and `ExperimentTest` are what kill any mutant of the hash input or the modulo.

## Testing an application that uses flags

### Unit tests: build the evaluator

`FlagEvaluator` is a plain object. Give it the configuration provider and a resolver that returns the context under
test:

```php
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;

function flags(array $flags, ?EvaluationContext $context = null): FlagEvaluator
{
    $resolver = new class($context ?? EvaluationContext::anonymous()) implements EvaluationContextResolver {
        public function __construct(private EvaluationContext $context) {}
        public function current(): EvaluationContext { return $this->context; }
    };

    return new FlagEvaluator([ConfiguredFlagProvider::fromConfiguration($flags)], $resolver);
}

$mailer = new OrderMailer(flags(['order-cancellation-in-email' => true]));
$mailer = new OrderMailer(flags(['order-cancellation-in-email' => false]));
```

Test both sides of every flag: each flag doubles the paths through the code it guards. A flag absent from the
array is off (not found), which is also what production does for an undeclared flag.

To test a rollout or an experiment deterministically, pick context identifiers whose buckets you know
(`Bucket::of(FlagName::of('my-flag'), 'tenant-1')->value`), or use `percentage: 0` and `percentage: 100`.

### Kernel tests: configure the test environment

```yaml
# config/packages/test/phil_feature_flags.yaml
phil_feature_flags:
    context_resolver: phil_feature_flags.context_resolver.anonymous
    doctrine: false
    toggle: false
    admin: false
    flags:
        new-checkout: true
```

Services are private; in a `KernelTestCase`, `static::getContainer()->get(FlagEvaluator::class)` reaches the
evaluator through the test container. To flip a flag inside one test without a database, configure an in-memory
`state_store` (a `FlagStateSource` service of the test environment whose map the test changes), and call
`FlagEvaluator::reset()` after changing it, since the evaluator memoizes per request.

### Testing adapters

- An `EvaluationContextResolver` is tested by asserting the context it builds for a given token or message.
- A `WritableFlagStore` or `WritableFlagDefinitions` adapter is tested for its race behaviour: a `compareAndSet()`
  with a stale expected value returns `false`, a second `insert()` returns `false`, a `replace()` against an old
  revision returns `false` — as `DoctrineStoreTest` does for the database store.
- A `ToggleAuditor` is tested for throwing when it cannot write, since that is what stops the change.

### Failing a build on expired flags

```php
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;

public function testNoFlagHasOutstayedItsPurpose(): void
{
    $now = new \DateTimeImmutable();
    foreach (static::getContainer()->get(FlagCatalog::class)->entries() as $entry) {
        self::assertFalse($entry->declared->metadata->isExpiredAt($now), $entry->declared->name->value.' has expired.');
    }
}
```

Or run `bin/console phil:feature-flags:list --fail-on-expired` in CI ([console.md](console.md)).

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/feature-flags-bundle/tests/Benchmark
```
