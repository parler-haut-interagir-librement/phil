# Testing

## How the bundle is tested

PHPUnit 13 unit tests under `tests/Unit/`, mirroring `src/`:

| Test | Covers |
|---|---|
| `tests/Unit/PhilBotDetectionBundleTest.php` | The extension alias is `phil_bot_detection`; loading the extension adds no service |
| `tests/Unit/Domain/Verdict/VerdictTest.php` | The two wire values; every other input (absent, empty, another case, whitespace, a longer payload, `true`, `unknown`) is `Unknown` and never throws; `isRobot()` |
| `tests/Unit/Domain/Friction/FrictionTest.php` | Exactly two cases and no refusal; `adds()` |
| `tests/Unit/Domain/Friction/FrictionPolicyTest.php` | `Human` adds nothing and `Robot` adds a step under both policies; `Unknown` under each policy |
| `tests/Unit/Application/VerdictHandlerTest.php` | A robot verdict is counted once and adds a step; a human verdict is not counted; **an unknown verdict is never counted, even when it adds friction** |
| `tests/Unit/Application/Fixture/InMemoryRobotVerdictCounter.php` | The in-memory counter the handler tests use |

The unit tests are pure: no container beyond the extension test, no network, no browser.

`tests/js/detectors.test.mjs` runs the browser side under Node's test runner: each detector against environments
that do and do not show its signal, their combination into one verdict, and the `verdict` controller writing it on
the root element and into its field. Stimulus is resolved to a stub (`tests/js/stimulus.stub.mjs`) by
`tests/js/register.mjs`:

```sh
node --import ./packages/bot-detection-bundle/tests/js/register.mjs --test packages/bot-detection-bundle/tests/js
```

A functional suite under `tests/Functional/` boots a real kernel, `tests/Functional/App/TestKernel.php`
(FrameworkBundle and the bundle under an empty `phil_bot_detection` block), with its cache in a directory of its
own under the system temporary directory. `BundleRegistrationTest` pins the alias, the empty reference
`config:dump-reference phil_bot_detection` prints, and the build failure an unknown key causes;
`VerdictHandlingTest` wires two surfaces as [configuration.md](configuration.md) shows, a sign-up form under
`robotOrUnknown()` and a payment under `robotOnly()`, each with its own handler and in-memory counter, and checks
that a verdict moves only its own surface's counter. It needs no environment variable:
`vendor/bin/phpunit packages/bot-detection-bundle/tests/Functional`.

## Running the tests

### From the monorepo root

```bash
vendor/bin/phpunit packages/bot-detection-bundle/tests
```

The browser detectors and their controller run under Node's test runner, Stimulus resolved to a stub by
`tests/js/register.mjs`:

```bash
node --import ./packages/bot-detection-bundle/tests/js/register.mjs --test packages/bot-detection-bundle/tests/js
```

### The bundle installed alone

What a project installing the bundle sees. The bundle has no sibling dependency and no `phpunit.xml`:

```bash
cp -R packages/bot-detection-bundle /tmp/bot-detection-bundle && cd /tmp/bot-detection-bundle
rm -rf vendor composer.lock
composer install
vendor/bin/phpunit --no-configuration tests
```

The monorepo runs this for every bundle with `composer packages`, together with
`composer-dependency-analyser` and the bundle's `composer-dependency-analyser.php`, whose one exception is the
declared but unimported `symfony/config` (required because `AbstractBundle` implements one of its interfaces).

In the monorepo, the bundle's code is also under the repository's static analysis, architecture rules, coding
standard and mutation testing.

## Testing your application

### Your counter adapter

| Assert | Why |
|---|---|
| `increment()` adds one to this instance's surface only | One instance per surface is how the count is split |
| Two instances wired for two surfaces do not share a count | Wiring mistakes merge surfaces silently |
| A backend failure does not throw out of `increment()` (or, if it does, your caller catches it) | A verdict must never end in a refusal |
| Nothing about the request reaches the stored key or value | The counter is the only trace a verdict leaves |

### Your surfaces

Use `InMemoryRobotVerdictCounter` (copy it from the bundle's tests, or write the same five lines) and a real
`VerdictHandler`:

```php
$counter = new InMemoryRobotVerdictCounter();
$handler = new VerdictHandler(FrictionPolicy::robotOrUnknown(), $counter);

// Submit the sign-up form with verdict=robot: one step of back-off, one robot verdict counted
// Submit with no verdict field: one step of back-off (robotOrUnknown), nothing counted
// Submit with verdict=human: no friction, nothing counted
```

And assert, in a functional test, that the verdict appears nowhere after the request: not in the session, not in
the logs, not in any table.

Installed on its own, the functional suite is skipped: with `symfony/event-dispatcher` required for development
only, Symfony's container leaves the event dispatcher out and no kernel boots. It runs from an application that
requires an HTTP kernel, such as the monorepo root.
