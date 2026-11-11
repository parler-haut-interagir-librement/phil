# Testing

| Area | What is covered |
|---|---|
| `PhoneType`, `PhoneNumberTransformer` | Regions, showing, empty fields, refusals |
| `ValidPhoneNumber` | Valid and invalid numbers, types, countries, messages and codes |
| `PhoneNumberColumnType`, `PhoneExtension`, `PhilPhoneBundle` | Storage, the filters, the configuration and the services |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle in
Canada: `PhoneBundleTest` stores a number, validates in `fr_CA`, submits the field and renders the filters. Installed
on its own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/phone-bundle/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/phone-bundle/tests/Benchmark
```
