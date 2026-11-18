# Changelog

All notable changes to `phil/profile-secure-input` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-18

### Added

- The metapackage `phil/profile-secure-input`, which installs the PHIL packages that guard what users send a Symfony
  application: `phil/url-bundle` `^0.1`, `phil/safe-http` `^0.2`, `phil/rate-limit-bundle` `^0.1`,
  `phil/spam-protection-bundle` `^0.2` and `phil/upload-scan-bundle` `^0.1`.
- The documentation: what each member does and why it belongs to the profile, the files each member brings, the
  stability a PHIL alpha needs, registering the bundles without Flex, how the constraints move, and how to leave out
  one member by requiring the others directly.
- A functional test that boots a Symfony kernel with the bundles of every member registered.
