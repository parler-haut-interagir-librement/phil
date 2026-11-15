# Testing

| Area | What is covered |
|---|---|
| `Url` | Every accepted and refused form, normalisation, length limits, `isOn()`, `display()`, `segments()`, the same results under a UTF-8 locale |
| `ServiceUrl`, `ServiceHosts` | Declared and undeclared hosts, names, IPv4 and IPv6 addresses, the rules kept from `Url`, references resolved on the same service or refused |
| `KnownUrlType` | Each domain of each kind, Mastodon profiles and look-alikes, labels |
| `UrlTypes`, `TypedUrl` | Detection among sets in order, an application's kinds before or after the international ones, refusals |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/url/tests
```

## Fuzzing

The fuzz target `url-service-addresses`, in `tests/Fuzz/`, feeds `Url::fromString()`, `ServiceHosts`,
`ServiceUrl::fromString()` and `resolve()` with inputs nobody wrote, from the seeds of
`tests/Fuzz/corpus/url-service-addresses`: each returns an address that reads back as itself, on a declared host and on
the same service, or throws `InvalidUrl`, never another exception. From the monorepo root:

```sh
composer fuzz -- url-service-addresses
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure `Url::fromString()` and `display()` on short, typical, international and long addresses, and on a refused one. From the monorepo root:

```sh
composer bench -- packages/url/tests/Benchmark
```
