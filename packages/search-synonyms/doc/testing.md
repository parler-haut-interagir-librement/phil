# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Area | What is covered |
|---|---|
| `SynonymSet`, `SynonymList`, `InvalidSynonyms`, `Violation` | Phrases normalized and kept once, every limit, languages, refusals |
| `SynonymValidator` | Overlaps and chains, a clean list |
| `SolrSynonymsReader`, `SolrSynonymsWriter` | Comments, blank lines, escapes, byte order mark and line breaks, every refusal with its line, the limits of a file, a list written and read back |
| `JsonSynonyms` | A document read and written back, every refusal, the limits |
| `FileSynonymStore`, `InMemorySynonymStore` | Lists saved and read, languages, a file left amiss, a directory that cannot be written |

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

## Fuzzing

The two readers declare fuzz targets, run on input nobody wrote from the seeds of `tests/Fuzz/corpus/<target>`:
whatever they read, they return a list or refuse it with `InvalidSynonyms`, and throw nothing else. From the monorepo
root:

```sh
composer fuzz -- solr-synonyms
composer fuzz -- json-synonyms
```
