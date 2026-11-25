# Changelog

All notable changes to `phil/http-cache-tags-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).

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
