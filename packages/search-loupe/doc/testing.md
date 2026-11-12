# Testing

| Area | What is covered |
|---|---|
| `LoupeIndex` | Every word, every filter, lists, a query's list of values, facets counted without their own filter, text-only search, pages, new fields and the rebuild, a new field on 150 documents on disk copied into a new directory a page at a time or left as it was when the copy fails, the fields file read again only when it changed, a write waiting for another process's change of directory, the fields and the rebuilds of another process, the fields or the directory kept amiss, the lock held during the switch, one Loupe opened while nothing changes, the index directory created or reported, memory |

The tests write their indexes in a temporary directory of their own and remove it; a PHP warning that is not
silenced fails them.

```sh
vendor/bin/phpunit packages/search-loupe/tests
```

## Benchmarks

The phpbench subjects in `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/search-loupe/tests/Benchmark
```
