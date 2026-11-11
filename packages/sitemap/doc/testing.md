# Testing

| Area | What is covered |
|---|---|
| `Url`, `Alternate`, `Image`, `ChangeFrequency` | Every field and its bounds, the addresses refused, `hreflang` spellings, the images of a page |
| `Sitemap` | Pages by section, one section only, files and numbered parts, the bounds of a file, sections refused |
| `XmlWriter` | The exact XML of a file and of an index, escaping, an index without dates, an index entry that is not an address |

```sh
vendor/bin/phpunit packages/sitemap/tests
```

## Benchmarks

`XmlWriterBench` writes files of 1,000, 10,000 and 50,000 pages, plain or with alternates and images. From the monorepo root:

```sh
composer bench -- packages/sitemap/tests/Benchmark
```
