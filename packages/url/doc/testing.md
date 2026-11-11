# Testing

| Area | What is covered |
|---|---|
| `Url` | Every accepted and refused form, normalisation, length limits, `isOn()`, `display()`, `segments()` |
| `KnownUrlType` | Each domain of each kind, Mastodon profiles and look-alikes, labels |
| `UrlTypes`, `TypedUrl` | Detection among sets in order, an application's kinds before or after the international ones, refusals |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/url/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `Url::fromString()` and `display()` on short, typical, international and long addresses, and on a refused one. From the monorepo root:

```sh
composer bench -- packages/url/tests/Benchmark
```
