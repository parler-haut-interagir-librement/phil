# Changelog

All notable changes to `phil/creation-disclosure` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## 0.1.0-alpha - 2026-11-12

### Added

- `DigitalSourceType`: the IPTC Digital Source Type vocabulary, its seventeen current codes and three retired ones,
  read from a code or an address over `http` or `https`, with its address, its English label, its replacement when
  retired, and what it says of generative AI.
- `Part`: the parts of a work declared on their own — text, image, audio, video, code.
- `CreationDisclosure`: how a work was made, part by part, immutable; undeclared parts kept visible; a retired type
  declared as its replacement; the generative AI view of a part and of the whole work, the AI-free view; storage as an
  array of codes and reading back, addresses included.
- `GenerativeAi`: the view — none, undeclared, possible, partial, full —, its combination over parts and its order.
- `DisclosureRequirement`: the parts a contest or a shop asks to declare, with an optional ceiling of generative AI,
  and the unmet parts of an entry.
- `Exception\CreationDisclosureException` and `Exception\InvalidDisclosure`, whose messages show at most 80 characters
  of what was given.
- A fuzz target for `CreationDisclosure::fromArray()`.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README, and its French mirror.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
