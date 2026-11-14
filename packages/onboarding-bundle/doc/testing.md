# Testing

## The bundle's tests

The suite `tests/Unit` tests the domain — the rules of the declarations, the order of the steps, the progress and its
times —; `Onboarding` over the in-memory store, a mock clock and a recording dispatcher — the checkers asked, the steps
completed, ticked, skipped, refused, the flow finished once, dismissed, reset, a user forgotten, the events —; the
listener of the events; the Doctrine and in-memory stores against one shared contract, on SQLite in memory, and the
mapping of the table; the endpoints, their CSRF check and their answers; the checklist and the tour rendered from the
templates; and the bundle's configuration, its compiler pass and its services. The suite `tests/Functional` boots a
kernel with DoctrineBundle on SQLite, TwigBundle, the components and `phil/settings-bundle`, and walks a member through
a flow: a form, an event, a checker, the JSON of the tour, and the tours turned off.

From the monorepo root:

```sh
vendor/bin/phpunit packages/onboarding-bundle/tests
```

The Stimulus controller is tested under Node's test runner, Stimulus resolved to a stub:

```sh
cd packages/onboarding-bundle
node --import ./tests/js/register.mjs --test tests/js
```

The benchmark `tests/Benchmark/OnboardingBench.php` measures the hot path: the progress of a user in a flow of twenty
steps, read from a table of a thousand users with one checker asked, and the catalog built from its declarations:

```sh
composer bench -- packages/onboarding-bundle/tests/Benchmark
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing code that uses flows

Configure the `memory` store in the test environment:

```yaml
# config/packages/test/phil_onboarding.yaml
phil_onboarding:
    store: memory
```

```php
$onboarding->complete('ada', 'author', 'profile');

self::assertSame(1, $onboarding->progress('ada', 'author')->done());
```

Or build `Onboarding` directly on a `FlowCatalog::fromDeclarations()`, an `InMemoryProgressRepository`, a
`ServiceLocator` of checkers and Symfony's `MockClock`.
