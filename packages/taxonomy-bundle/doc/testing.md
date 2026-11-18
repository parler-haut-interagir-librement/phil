# Testing

| Area | What is covered |
|---|---|
| The entities, `ResourceReference` | Every check and bound; paths, depth, moves and cycles; the mapping |
| `Taxonomy` with `DoctrineTaxonomyRepository` | On SQLite in memory: tags found, given, renamed, merged; categories created, translated, moved, merged with one read of the tree and one flush; names read in their languages only; branches; everything stored |
| `TagsType`, `CategoryType` | Text and lists; the tree indented in a language, its translations read once; choices given; several; the locale option |
| `PhilTaxonomyBundle` | Services and the mapping |
| `BinaryKeysListener` | The keys in `utf8mb4_bin` in the schema generated for MySQL and MariaDB, the default collation on SQLite, the options a field already has kept |

The suite `tests/Integration` runs `BinaryKeysListener` on a real MySQL 8.4 and PostgreSQL 17: four slugs that differ only by case or accent are four tags, each found by its own slug only, and on
MySQL the keys are in `utf8mb4_bin` and the tables of the previous version reach it through the schema diff. Each
server's test reads its URL from `PHIL_TEST_MYSQL_URL` or `PHIL_TEST_POSTGRESQL_URL` and is skipped without it:

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/taxonomy-bundle/tests/Integration
```

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
