# Changelog

All notable changes to `phil/error-tracking` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `ErrorEvent`: an identifier, an instant, a `Source`, a `Severity`, a chain of `ExceptionRecord` with their `Frame`s,
  a release, an environment, a `RequestSummary` and tags, every text bounded; `title()`, `culprit()`, `with()`,
  `toArray()` and `fromArray()`.
- `Frame`: a file relative to the project, a line, a function with the paths and lines of closures and anonymous
  classes removed, whether it is the application's; the arguments of a call are never kept.
- `RequestSummary`: method, address, route and headers by lower-case name, bounded; `anonymized()` keeps the client's
  address only as a `phil/ip-anonymizer` strategy leaves it.
- `EventFactory`: events from a throwable and its previous ones, or from a message and frames, dated by a PSR-20
  clock, marked with the release and environment, chains and traces bounded.
- `MessageNormalizer` and `Fingerprinter`: the SHA-256 of each exception's type, message without its variable values,
  and file and function of its first ten frames of the application.
- `Scrubber`: sensitive headers and tags, every value of the query, e-mail addresses, credentials of an address,
  `Bearer` and `Basic` tokens and sensitive `name=value` pairs filtered; fields of the application added by name.
- `Sampler`, a share of the events drawn at random; `CaptureThrottle`, the port that bounds the events of a key.
- `GroupStatus`, `Occurrence` and `ErrorGroup`: open, resolved on the last release seen, ignored, regressed when seen
  on another release; first events and regressions alert.
- `ErrorStore` and `GroupAlerter`, the ports of the groups and of the alerts; `ErrorTracker` and its `Capture`.
- `ErrorTrackingException`, implemented by `InvalidEvent` and `InvalidOption`.
- The benchmarks of the fingerprint, the normalizer and the scrubber.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
