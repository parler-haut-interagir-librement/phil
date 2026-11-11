# Testing

| Area | What is covered |
|---|---|
| `PhoneNumber` | Parsing in 15 countries, prefixes, extensions, refusals, regions and types, the four formats, storage |
| `Territories`, `Territory` | Finding plans, national and international prefixes, types, leading digits |
| `MetadataImporter` | A fixture of libphonenumber's XML turned into data, and refusals |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/phone/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/phone/tests/Benchmark
```
