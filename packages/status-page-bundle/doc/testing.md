# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The domain, the records reader, the builder, the JSON and the feed, the push, the sources with a stand-in of the read model of `phil/monitoring-bundle`, the stores and publishers on a temporary directory and an in-memory Flysystem, the configuration and its passes. |
| `tests/Functional` | A small kernel with FrameworkBundle, Messenger, the Scheduler, TwigBundle and Twig Component, the recipes of the kit copied where `ux:install` puts them, a clock fixed in time: the page, the JSON and the feed over HTTP, the command, the message and the schedule writing a snapshot to a directory. |

Nothing needs a network or a server. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

`tests/Fuzz/status-records.php` reads incidents and maintenances from JSON nobody wrote: the records read, or are
refused with `InvalidStatusRecord`, and nothing else is thrown. From the monorepo root:

```sh
composer fuzz -- status-records --budget=60
```

## Testing an application

`StatusPageBuilder` takes a PSR-20 clock: give the test kernel a `MockClock` as `clock` and the page is the same on
every run. A directory target under the test's temporary directory makes `phil:status-page:push` observable without a
network.
