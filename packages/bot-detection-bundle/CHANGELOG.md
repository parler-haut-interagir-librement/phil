# Changelog

All notable changes to `phil/bot-detection-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-17

The next version is 0.1.1-alpha: documentation only.

### Changed

- The README, in English and in French, says what the package does, without the wording of its first drafts.
- `CONTRIBUTING.md` says what the package does and how to work on it, without the wording of its first drafts.
- The header of `assets/src/detectors.js` and the page on browser signals describe the detectors without that wording.

## 0.1.0-alpha - 2026-11-11

### Added

- `Domain\Verdict\Verdict`.
- `Domain\Friction\Friction`, `Domain\Friction\FrictionPolicy`.
- `Domain\Counter\RobotVerdictCounter` (port, implemented by the application).
- `Application\VerdictHandler`.
- The browser detectors (`assets/src/detectors.js`) and the Stimulus controller `verdict`, which writes `human` or
  `robot` on the root element and into its field; tested under Node's test runner (`tests/js`).
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
