# Changelog

All notable changes to `phil/profile-editorial` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-18

### Added

- The metapackage `phil/profile-editorial`, which installs the PHIL packages that publish editorial content in a
  Symfony application: `phil/markup-bundle` `^0.1`, `phil/citations` `^0.1`, `phil/footnotes-sidenotes` `^0.1`,
  `phil/content-warnings-bundle` `^0.1` and `phil/structured-data-bundle` `^0.1`.
- The documentation: what each member does and why it belongs to the profile, the files each member brings, the
  stability a PHIL alpha needs, registering the bundles without Flex, how the constraints move, and how to leave out
  one member by requiring the others directly.
- A functional test that boots a Symfony kernel with the bundles of every member registered.
