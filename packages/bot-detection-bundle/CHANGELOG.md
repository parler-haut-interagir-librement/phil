# Changelog

All notable changes to `phil/bot-detection-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Domain\Verdict\Verdict`.
- `Domain\Friction\Friction`, `Domain\Friction\FrictionPolicy`.
- `Domain\Counter\RobotVerdictCounter` (port, implemented by the application).
- `Application\VerdictHandler`.
- The browser detectors (`assets/src/detectors.js`) and the Stimulus controller `verdict`, which writes `human` or
  `robot` on the root element and into its field; tested under Node's test runner (`tests/js`).
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
