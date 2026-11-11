# Changelog

All notable changes to `phil/url` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.3-alpha - 2026-11-11

### Added

- `Url::fromString($text, allowLocalhost: true)` accepts `localhost` and the names under `.localhost` (RFC 6761), for
  development and tests; an IP address and any other single name stay refused, `http://127.0.0.1/`,
  `http://localhostx/` and `http://intranet/` among them.

### Changed

- By default, a name under `.localhost`, `http://app.localhost/`, is refused like `localhost`: both name the loopback
  address.

## 0.1.2-alpha - 2026-11-11

### Changed

- `Url::fromString()` reads an address as a browser does, on PHP 8.5's URI extension, `Uri\WhatWg\Url`, by the
  WHATWG URL Standard: in the path, a backslash is a slash and the segments `.` and `..` are resolved, written plainly
  or escaped (`%2e`, `%2E`, `.%2e`, `%2e.`); the characters the path, the query or the fragment cannot hold, `<`, `>`,
  `"` and the letters outside ASCII among them, are percent-encoded, each part by its own set of the standard. An
  escape already written stays as written, `%2F` a character of its segment and never a separator. Its own rules
  stay: `http` and `https` only, no user name nor password, an empty one included (`https://@example.com/`), no port
  0, no IP address, the host rules of 0.1.1-alpha. The 2048 characters at most hold for the normalised address too.
  An IPv4 address written short, `http://127.1/`, is named `127.0.0.1` in the message of `InvalidUrl`; a top-level
  domain of digits, `https://shop.123/`, is refused as no web address, not as no host name.
- `Url::display()` shows the escaped letters of the path and the query as letters, `example.com/thé`; an escape of
  ASCII, an invisible character, a space or a control stays escaped.
- `phil/url` requires `ext-uri`, bundled with PHP 8.5.

### Security

- `path`, `segments()` and `equals()` now give the path a browser opens: `https://example.com/docs/../admin`,
  `/docs/%2e%2e/admin` and `/docs\..\admin` have the path `/admin`, so an application that allows paths by their
  prefix, `/docs/`, can no longer be bypassed. `<`, `>` and `"` are percent-encoded in `toString()`.

## 0.1.1-alpha - 2026-11-11

### Fixed

- `Url::fromString()` throws `InvalidUrl`, not ICU's `ValueError`, for a host made only of dots — `https://.`,
  `https://...`, `https://./path` — and refuses a host ending with two dots, `https://example.com../`, whose last
  label is empty; a single trailing dot is still dropped.

## 0.1.0-alpha - 2026-11-11

### Added

- `Url`: `Url::fromString()` refuses a host that is an IP address — a top-level domain of digits — and port 0;
  `Url::display()` shows an international host in its own letters, `bücher.example`, unless ICU's spoof checker finds
  it suspicious, `аpple.com` in Cyrillic, which stays in ASCII.
- `UrlType`, `KnownUrlType`, `TypedUrl` and `UrlTypes`; `KnownUrlType::Mastodon` accepts no address on the domains of
  the other kinds, nor on `medium.com`, `substack.com`, `figma.com`, `vsco.co`, `behance.net`, `ko-fi.com` and
  `buymeacoffee.com`, whose profiles are also written `/@name`.
  `KnownUrlType::Pinterest` accepts only the domains Pinterest holds, never another `pinterest.<tld>`.
  `KnownUrlType::YouTube` accepts `youtube-nocookie.com` too.
- `CookielessVideo`, which turns a YouTube video into its player on `youtube-nocookie.com`, its start time kept, and a
  Vimeo video into its player with `dnt=1`.
- `UrlException` and `InvalidUrl`, with `InvalidUrl::port()`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
