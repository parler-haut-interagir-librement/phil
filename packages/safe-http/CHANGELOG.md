# Changelog

All notable changes to `phil/safe-http` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.2-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency (`symfony/http-client-contracts` 3.8, `symfony/service-contracts` 3.8).

## 0.2.1-alpha - 2026-11-15

### Added

- `SafeHttpClient::send($method, $url, $headers, $body, $rule)`: a request of any method, with its headers and body,
  under the guards of `get()` — every address checked, the private networks refused, the time, the redirects and the
  bytes of the answer bounded. The method is letters only, put in capitals; `CONNECT` and `TRACE` are refused. A
  header's name is a token, never digits only, which PHP keeps as an integer key, never `Accept`, `User-Agent`, a header
  of the connection or of the framing, nor a `Proxy-` header; its value is one line; the headers are held to
  `maxHeaderBytes` and `maxHeaders`, the body to `maxBytes`. Only a request of `GET`, `HEAD` or `OPTIONS` without a body
  follows redirects; the headers given never leave the scheme, host and port of the first address. No message names a
  header's value.
- `InvalidRequest`, refused before any request, and `RefusedRedirect`, a redirect answered to a request that follows
  none.
- Sending a request with headers and a body, its redirects and its credentials, in `doc/reading.md`.

### Fixed

- `SafeResponse::mediaType()`, `mediaTypeOf()` and `charset()` read by ASCII classes: under `LC_CTYPE` set to a UTF-8
  locale, PCRE took bytes outside ASCII for letters and spaces, and `text/h\xE9ml` was a media type.

## 0.2.0-alpha - 2026-11-14

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
