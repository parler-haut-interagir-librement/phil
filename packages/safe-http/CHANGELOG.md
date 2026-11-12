# Changelog

All notable changes to `phil/safe-http` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `SafeHttpClient`: `get()` and `head()` behind Symfony's `NoPrivateNetworkHttpClient`, whatever the transport, the
  redirects followed one by one and each checked, the silence and the whole duration bounded across the redirects,
  the body read in chunks up to its bytes or a stop mark, no proxy of the environment; `resolve`, fixed addresses still
  checked; `reset()`.
- `Limits`: the silence, the whole duration, the bytes, the redirects and the `User-Agent`, each bounded.
- `ReadRule`: the `Accept`, the media types whose body is read and the stop mark; `htmlHead()`.
- `SafeResponse`: the address that answered, the redirects, the status, the headers, the media type, the character set,
  the body and whether it is complete.
- `AddressGuard`: a first address and a redirect read by phil/url — an HTTP or HTTPS address of a host name, no IP
  address, no credentials —, a redirect resolved against the address that answered.
- `SafeHttpException`, `RefusedAddress`, `TooManyRedirects`, `FetchFailed` and `InvalidLimits`.
- The fuzz target of the guard.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
