# Testing

| Area | What is covered |
|---|---|
| `Node`, `Reference`, `LanguageString`, `LanguageTag`, `Id` | Every kind of value and how it is written, data read back, merging, depth, terms and identifiers refused, stable identifiers, language tags |
| `Graph`, `JsonLd` | Merging by `@id`, nested nodes lifted, languages, the bound of nodes, the escaping of the script element, nonces |
| `JsonLdReader` | Elements read, merged and skipped, documents of every shape, the bounds of a page |
| `Validator`, `Rules`, `Report` | Required and recommended properties, parents and loops, values of each format, references and untyped nodes |
| The factories | Every argument of each, and a graph of them the validator accepts |
| `Schema` | Each method gives the node of its factory, the warnings left to the validator, and each kind of error refused, in a nested node too |

```sh
vendor/bin/phpunit packages/structured-data/tests
```

## Fuzzing

`tests/Fuzz/jsonld-reader.php` gives the reader inputs nobody wrote, as a page and as one document, then validates and
writes what it read: nothing but `InvalidStructuredData` may come out, and no warning. Its seeds are in
`tests/Fuzz/corpus/jsonld-reader`. From the monorepo root:

```sh
composer fuzz -- jsonld-reader
```

## Benchmarks

`JsonLdBench` assembles and writes the graph of a page — a site, an organisation, a page and its breadcrumb — with 0,
20 and 500 events that each give the organisation again. From the monorepo root:

```sh
composer bench -- packages/structured-data/tests/Benchmark
```
