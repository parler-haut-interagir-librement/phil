# Testing

| Area | What is covered |
|---|---|
| `KnownMessenger` | Every handle form of every service, phone numbers outside their plan or with an extension refused, their links, foreign links refused, display, links, names |
| `Handle`, `Messengers` | Canonical handles, equality, refusals, detection in order, an application's services first |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/instant-messenger/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `Messengers::detect()` over every known service, for inputs read by the first service, a later one, or none. From the monorepo root:

```sh
composer bench -- packages/instant-messenger/tests/Benchmark
```
