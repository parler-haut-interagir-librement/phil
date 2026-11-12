# Changelog

All notable changes to `phil/rate-limit` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `RateLimitGuard`, the compiler pass that fails the container build on a rate limit that would not limit:
  `requireComponent()` refuses one enabled without `symfony/rate-limiter`, `watch()` notes each cache pool or storage
  by the configuration path that names it, with `single_server` and the option that turns the limit off, and the pass
  refuses in the `prod` environment a store local to each server; every bundle shares one hidden parameter, checked
  once.
- `StoreLocality`: a pool on the filesystem, APCu, array or system adapter of `symfony/cache`, by parent or class, the
  `InMemoryStorage` of `symfony/rate-limiter` and a `CacheStorage` given such a pool are local; aliases and parents are
  followed, at most 32 services, and a loop is left to the container.
- The documentation in `doc/`, one file per topic, indexed by the README, and its French mirror.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
