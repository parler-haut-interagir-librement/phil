# Testing

| Area | What is covered |
|---|---|
| The entities, `ResourceReference` | Every check and bound; paths, depth, moves and cycles; the mapping |
| `Taxonomy` with `DoctrineTaxonomyRepository` | On SQLite in memory: tags found, given, renamed, merged; categories created, translated, moved, merged with one read of the tree and one flush; names read in their languages only; branches; everything stored |
| `TagsType`, `CategoryType` | Text and lists; the tree indented in a language, its translations read once; choices given; several; the locale option |
| `PhilTaxonomyBundle` | Services and the mapping |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite and the bundle: it tags and
categorizes a resource, picks a category in `fr_CA`, and reads the French help.

```sh
vendor/bin/phpunit packages/taxonomy-bundle/tests
```

## Benchmarks

The phpbench subjects in `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/taxonomy-bundle/tests/Benchmark
```
