# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network.

- `FractionTest` checks the exact arithmetic, every rounding mode, and the steps whose exact fraction no longer fits,
  against values computed with arbitrary precision.
- `UnitRegistryTest` checks the declared families and every definition the registry refuses.
- `MeasurementTest` checks the conversions against the definitions of the units, the temperatures and their
  differences, the reciprocal units and the splits.
- `Cldr/UnitPreferencesTest` and `Cldr/UnitFormatterTest` read the CLDR data of the package; `Cldr/CldrImporterTest`
  imports a small cldr-json into a temporary folder that it removes.
- `Property/ConversionRoundTripTest` checks properties on values drawn from a seeded generator, so that a failure is
  replayed: a value converted to any unit of its family and back is the value again, a conversion keeps the order of
  two values (the other way for a reciprocal unit), and a sum is the same in every unit.

In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz targets

| Target | What it reads | Seeds |
|---|---|---|
| `measurement-parse` | `UnitRegistry::parse()` on any bytes | `tests/Fuzz/corpus/measurement-parse` |
| `measurement-definitions` | `UnitRegistry::fromDefinitions()` on any JSON | `tests/Fuzz/corpus/measurement-definitions` |

Each read returns a measurement or a registry, or throws a `MeasurementException`: nothing else escapes, a measurement
read back from its text is the same, and every unit built comes back from its standard unit.

```sh
composer fuzz -- measurement-parse
```

## The benchmarks

`tests/Benchmark/ConversionBench.php` measures the hot paths of a product feed: a thousand weights converted to the
unit of a channel, written in a locale, given their preferred unit, a thousand conversions whose exact fraction no
longer fits, and a registry built with an application's units.

```sh
composer bench -- packages/measurement/tests/Benchmark
```
