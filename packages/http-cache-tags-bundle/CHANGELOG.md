# Changelog

All notable changes to `phil/http-cache-tags-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

### Added

- `http.client`, `http.timeout` and `http.max_duration`: the HTTP client of the purgers, `http_client` by default, and
  how long a purge call may take, 5 and 10 seconds by default; `TIMEOUT` and `MAX_DURATION` of `VarnishPurger` and
  `CloudflarePurger`, and their last two arguments, optional.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.

### Security

- `VarnishPurger` and `CloudflarePurger` called without a timeout: a slow cache held a worker until
  `default_socket_timeout`, 60 seconds. Every call waits 5 seconds at most for a byte and lasts 10 at most by default.

## 0.1.0-alpha - 2026-11-11

### Added

- `CacheTag` (value), with `tryOf()`, and `InvalidCacheTag`.
- `ResponseTags`, `Invalidator`, `CacheTagPurger` and `CacheTagResolver`.
- `VarnishPurger` and `CloudflarePurger`, which purge every cache and every chunk before throwing one `PurgeFailed`
  that names each failure, transport errors included.
- `PhilHttpCacheTagsBundle`, extension alias `phil_http_cache_tags`, keys `header`, `purger`, `varnish`,
  `cloudflare` and `invalidate_entities`.
- The `#[CacheTag]` attribute; `ResponseTagsListener`, which leaves out a tag whose route value makes it no tag.
- `InvalidateOnFlushListener`; `InvalidationFlushListener`, which purges at the end of a request, a command or a
  Messenger worker's message, and logs a refused purge with the container's `logger`.
- `CacheTagExtension`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
