# Changelog

All notable changes to `phil/url` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

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
