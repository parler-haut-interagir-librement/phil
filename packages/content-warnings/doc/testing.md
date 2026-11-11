# Testing

| Area | What is covered |
|---|---|
| `Key`, `LocalizedText` | Valid and invalid keys and locales, trimming, the order labels are read in, merging |
| `Catalogue`, `Category`, `Theme` | Order, lookups, bounds, refusals |
| `CatalogueLoader`, `BaseCatalogue` | Building, completing, merging, removing, replacing, files and their bounds, every refusal, the six languages of the base list |
| `ContentWarning`, `ContentWarnings` | Notes, order, replacing in place, bounds, the round trip and every way stored warnings are refused |
| `Severity`, `Display`, `Preference`, `WarningPreferences` | Ordering, severity thresholds, the theme before the category before the default, the round trip and refusals |
| `WarningMatcher`, `Decision` | No warnings, the strictest display and its warnings, matching without a catalogue |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/content-warnings/tests
```

## Benchmarks

`WarningMatcherBench` measures a decision for none, three and a hundred warnings, and loading the base list. From the
monorepo root:

```sh
composer bench -- packages/content-warnings/tests/Benchmark
```
