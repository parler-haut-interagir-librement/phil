# Changelog

All notable changes to `phil/ujo-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilUjoBundle`, configured under `phil_ujo`: `allow_raw`, `max_bytes`, `max_depth`, `max_blocks`,
  `max_html_length` and `max_raw_length`.
- The services of `phil/ujo` — `BlockTypeRegistry`, `Normalizer`, `HtmlRenderer`, `PlainText`, `JsonDecoder` and the
  public `Ujo` —, each under its class name, by `UjoServices`.
- `UjoExtension`: the `ujo_html` and `ujo_text` Twig filters and functions, registered when Twig is installed.
- `UjoType` and `UjoTransformer`: a field holding UJO JSON, kept as canonical JSON or as a `Document`, an invalid
  document failing with the library's message.
- `ValidUjo` and `ValidUjoValidator`: a valid document at the constraint's validation level — its shape by default,
  or what it needs to be published —, of accepted block types when named, with messages in English and French.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
