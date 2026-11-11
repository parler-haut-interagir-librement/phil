# Testing

| Area | What is covered |
|---|---|
| `CacheTag`, `ResponseTags`, `Invalidator` | Every check; once each; purged once; no purger |
| `ResponseTagsListener`, `#[CacheTag]`, `cache_tag()`, `InvalidationFlushListener` | Route parameters, missing ones, the header, sub-requests, the end of a request |
| `VarnishPurger`, `CloudflarePurger`, `InvalidateOnFlushListener` | The requests sent, chunks of a hundred, the status bounds; a flush's entities |
| `PhilHttpCacheTagsBundle` | Services, headers, purgers, refused settings |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle purging a
Varnish that writes down what it is asked, with sessions and a listener that tags late: `HttpCacheTagsBundleTest`
renders a tagged page, the late tag included, and purges it when a flush renames it, once the request ends.

```sh
vendor/bin/phpunit packages/http-cache-tags-bundle/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/http-cache-tags-bundle/tests/Benchmark
```
