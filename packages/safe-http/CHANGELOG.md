# Changelog

All notable changes to `phil/safe-http` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Added

- `Limits::$maxHeaderBytes`, 64 KiB by default, and `Limits::$maxHeaders`, 100 by default: the headers of an answer,
  counted as the transport reports them and once more when they are all in; past either, the transfer is aborted and
  the read throws `FetchFailed`.
- `FetchFailed::headersTooLarge()`.
- The bounds each transport of Symfony HttpClient puts on the headers by itself, in `doc/reading.md`.

### Changed

- An answer whose headers pass 64 KiB or 100 headers now fails with `FetchFailed::headersTooLarge()`, where it was
  read; raise `Limits::$maxHeaderBytes` or `Limits::$maxHeaders` to read it again.

### Fixed

- A chunk larger than the bytes that remain is cut before it joins the body: the body held never exceeds `maxBytes`,
  where it could pass it by one chunk of the transport. The body returned and the errors are unchanged.

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
