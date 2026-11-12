# Changelog

All notable changes to `phil/publishing` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `PagePublished` and `PageRetired`: a page that goes online or offline, with its canonical address (a
  `Phil\Url\Url`), its language as a BCP 47 tag, an « indexable » flag and the identifier its owning package gives
  it; immutable, checked on construction.
- `Contract\PageEvent`, the four read-only fields both events carry.
- `Exception\InvalidPageEvent`: a language that is not a BCP 47 tag of 35 characters at most, or an owner identifier
  that is empty, longer than 255 bytes, or holds a control character or surrounding spaces.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
