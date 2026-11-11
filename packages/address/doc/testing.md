# Testing

| Area | What is covered |
|---|---|
| `Address`, `AddressArray`, `Countries` | Normalising, refusals, the round trip |
| Formats and subdivisions | Formats, local formats, generic format, three levels, names in their languages |
| `AddressValidator` | Required and unused fields, postal codes of countries and regions, subdivisions by level |
| `AddressFormatter` | Canada, the United States, France, Japan in both formats, Brazil, Ukraine, China, Peru, postal labels |
| `DataImporter` | A fixture of commerceguys/addressing's data turned into data, and refusals |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/address/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/address/tests/Benchmark
```
