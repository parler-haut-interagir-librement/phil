# Testing

## The bundle's tests

The suite `tests/Unit` tests the guard — once per throwable, ignored classes, a failed capture logged once and never
looping —; the parser of the browsers' reports on both stack formats, its bounds and what it refuses; the receiver and
each of its checks; the summary of a request, behind a proxy too, and the listener of requests; the listeners of workers and commands, the
queued alert and its handler; the chat message; the Monolog handler, its levels and the array a redacting processor
leaves in place of an exception; the DBAL store on SQLite, with the
race of two first events, the cap and the purge; the administration pages rendered with their real templates; the
throttle; the health check, the notified alert and its subject, the named limiter; the guard of the rate limits and the
pass of the alerts; and every definition the configuration wires.

The suite `tests/Functional` boots a kernel with FrameworkBundle and DoctrineBundle on SQLite and serves requests as a
worker would: a failing page grouped, scrubbed and notified, the token of its path and the addresses of the proxies
never kept, a 404 left out, a browser's report kept, the records of the log redaction of `phil/privacy-bundle` grouped
by their exception, the purge command, the health check and the named limiter. A second kernel is a plain install —
FrameworkBundle, DoctrineBundle and the bundle, with a command bus and a query bus —: booted in a process of its own
where the classes of phil/monitoring-bundle cannot be loaded, it compiles without the health check, and the queued
alerts are handled on the default bus only.

The tests of the Stimulus controller and its helpers, in `tests/js`, run under Node's own test runner, Stimulus
resolved to a stub by `register.mjs`. `tests/Functional/JavaScriptTest.php` runs them with the other suites, so that
`composer test` fails when one of them fails; on a machine without Node it is skipped and says so.

From the monorepo root:

```sh
vendor/bin/phpunit packages/error-tracking-bundle/tests
```

Or the scripts alone, from the package's directory:

```sh
node --import ./tests/js/register.mjs --test tests/js/
```

The bundle is held to 100 % covered mutation score by Infection.

## The fuzz target

`tests/Fuzz/browser.php` gives the parser of the browsers' reports what nobody wrote, from the corpus of
`tests/Fuzz/corpus/browser`: a body is read or refused with `InvalidBrowserError`, nothing else, and every report read
keeps within the bounds the parser promises. `composer fuzz browser` runs it for its budget.

## Testing an application that captures errors

Read what the store kept through `ErrorGroupReader`, after serving a request in a kernel test:

```php
$client->request('GET', '/export');

$groups = self::getContainer()->get(ErrorGroupReader::class)->groups(null, 10);
self::assertSame('RuntimeException: The export failed', $groups[0]->title);
```

In a test environment that should keep nothing, set `sample_rate: 0.0`.
