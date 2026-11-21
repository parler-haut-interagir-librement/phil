# Changelog

All notable changes to `phil/profile-secure-input` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-20

An application installs the profile without `doctrine/dbal` and without sessions. A
patch version and not a minor one: the members keep their minor versions, only their floors move.

### Changed

- It requires `phil/url-bundle` 0.1.3 or later, which boots without `doctrine/dbal`, and `phil/upload-scan-bundle`
  0.1.1 or later, whose quarantine page checks its token by the origin of the request, without a session.
- The documentation no longer asks the application for `doctrine/dbal`, nor for CSRF protection with sessions.

## 0.1.0-alpha - 2026-11-18

### Added

- The metapackage `phil/profile-secure-input`, which installs the PHIL packages that guard what users send a Symfony
  application: `phil/url-bundle` `^0.1`, `phil/safe-http` `^0.2`, `phil/rate-limit-bundle` `^0.1`,
  `phil/spam-protection-bundle` `^0.2` and `phil/upload-scan-bundle` `^0.1`.
- The documentation: what each member does and why it belongs to the profile, the files each member brings, the
  stability a PHIL alpha needs, registering the bundles without Flex, how the constraints move, and how to leave out
  one member by requiring the others directly.
- A functional test that boots a Symfony kernel with the bundles of every member registered.
