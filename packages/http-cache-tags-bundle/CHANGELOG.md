# Changelog

All notable changes to `phil/http-cache-tags-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
