# Changelog

All notable changes to `phil/svg-blocks` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `BlockMarkup`: the `math`, `mermaid`, `chart` and `qrCode` blocks of a UJO document drawn when it is saved, nested
  blocks included, each drawing kept under `rendered` with its key; current drawings kept, stale or forged ones
  dropped and drawn anew, deferred types listed as pending, failures listed with their reason, drawings bounded to
  1 MiB; `preview()` and `draws()`.
- `Rendered`, the result of a pass.
- `Signer`: versioned HMAC-SHA256 keys of the type, the source and the markup, a secret of 32 bytes or more, checked in
  constant time; `trusts()` is the closure of `HtmlRenderer::withStoredMarkup()`.
- `Source`: what each type is drawn from.
- `QrCodeRenderer`, `ChartRenderer` and `MathRenderer` over `phil/qr-code`, `phil/svg-chart` and `phil/latex-mathml`.
- The `MermaidService` port, `HttpMermaidService` with a deadline, no redirect and a size limit, and
  `MermaidRenderer`, whose answer goes through `phil/svg-sanitizer` with `<style>` elements kept, every rule of
  Mermaid's theme placed under the diagram's root, named from its content so that two diagrams on one page never
  share a rule.
- `RenderingFailed` and `InvalidConfiguration`, under the `SvgBlocksException` interface.
- Unit tests, an integration test against the Mermaid service at `PHIL_MERMAID_URL`, and a bench of reading.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
