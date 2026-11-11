# Testing

## The bundle's tests

`tests/Unit` tests each class alone: the accepted currencies, the locale-aware decimal, the form type through a
form factory, each constraint with Symfony's `ConstraintValidatorTestCase`, the Twig filter in an environment, the
column type on a platform, and the bundle's configuration, services and prepended Doctrine configuration.

`tests/Functional` boots a kernel in `fr_CA` with FrameworkBundle, TwigBundle and DoctrineBundle on SQLite: a price
entered in a form, the constraints with their French messages, the filter, and a `Product` whose price is stored
in two columns, read back and summed.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/money-bundle/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Mutation testing kills every mutant of `src/`.

## Testing an application's forms and entities

- **Set the locale** the test reads in: `Locale::setDefault('fr_CA')`, restored after the test. An amount typed
  as `1 234,50` reads differently in `en`.
- **Submit strings**, as a browser does: `['amount' => '19,99', 'currency' => 'CAD']`.
- **Compare amounts with `equals()`** or on `minorUnits`, not with `assertSame()` on two objects.
- **Keep a test of an entity round trip** when an embedded `Money` is added: it catches a missing mapping or column
  type before production.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `format_money` over listings of one to five hundred prices, with the kept formatter and with a new one per price. From the monorepo root:

```sh
composer bench -- packages/money-bundle/tests/Benchmark
```
