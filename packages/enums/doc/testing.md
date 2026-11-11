# Testing

## The library's tests

`tests/Unit` holds one test class per trait, a test of `AbstractMetaProperty`, and `Meta\CoverageTest`, which
pins what the trait tests leave out: exact error messages, custom accessor names, meta properties declared by a
trait, a one-case enum, and the default labels of `stringOptions()` for every shape of case name, letters outside
ASCII included. The fixtures under `tests/Fixture` are enums and meta properties built for those cases.

From the root of the monorepo:

```sh
vendor/bin/phpunit packages/enums/tests
```

Installed on its own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Mutation testing holds the library to the same bar as a domain layer: every mutant of `src/` outside the PHPStan
extensions is killed. The mutants excused in the configuration are those no test can see: a visibility change of
`customMethodName()`, the caches of the meta properties, of deprecated cases and of transitions, which hold answers
that reading again gives unchanged, and the filter of aliases to strings, which only tells PHPStan their type.

The PHPStan rules are checked by the analysis itself: the fixtures `Incomplete`, `Misdeclared`, `OrdinalLeak`,
`TwoDefaults` and `Level` carry `@phpstan-ignore` comments naming the identifiers they must raise, and an ignore
that matches nothing fails the analysis.

## Testing your enums

The traits need no setup: call the methods on your enum in a plain PHPUnit test. For metadata, assert the accessor
of each case, the default value of a case without the attribute, and `fromMeta()` on a value no case carries.

For required metadata, assert that no case misses any, so that a case added without its label fails the suite:

```php
self::assertSame([], Reflection::missingRequiredMeta(Status::class));
```

For `random()`, pass a `Randomizer` on a seeded engine — `new Randomizer(new Xoshiro256StarStar(42))` — and assert
the draw it gives; or an engine of your own that hands out chosen numbers, to assert which case each draw lands on.
For an `EnumMap` built by `total()`, building it in a test is enough: a case the callback forgets fails there.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/enums/tests/Benchmark
```
