# Changelog

All notable changes to `phil/svg-blocks-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- The configuration: the signing secret, the level of the QR codes, the size of the charts, the limits of formulas
  and their budget of macros, the Mermaid service (address, timeout, background or not, documents), and the preview.
- Formulas drawn with every construction of `phil/latex-mathml`: macros, colours, links, boxes, equation numbers,
  rules and chemical notation.
- `StoredMarkupTypeExtension` and `StoredMarkupTransformer`: the blocks of the documents of `UjoType` and of the block
  editor's field drawn when submitted, the drawings a client sent dropped; `svg_blocks: false` turns it off.
- The `HtmlRenderer` of `phil/ujo-bundle` decorated to write the trusted drawings.
- `PreviewController` and `PreviewRequest`: the preview endpoint behind the stateless CSRF token `phil_svg_blocks`,
  its route, and the container stopped when the CSRF protection is off.
- The `preview` Stimulus controller, fetched when its element appears, its path given to AssetMapperBundle when it is
  enabled, with its pure modules tested under Node.
- The background pass: `RenderDeferredMarkup`, carrying the subject alone, its handler, `MarkupScheduler` and the
  `MarkupDocuments` port; without documents or a bus, the handler is removed before the compiler pass of
  `symfony/messenger` wires the handlers.
- The Mermaid service in `docker/mermaid`: its security review in `doc/diagrams.md`; a Dockerfile run as an
  unprivileged user that owns nothing, without install scripts; an HTTP server with a concurrency limit, a bounded
  queue, deadlines that close the browser, limits on the body, the code and the SVG, strict Mermaid whose directives
  cannot change its security, Chromium without any way out; and a compose file read-only, without capabilities, under
  the default seccomp profile, with limits of memory, CPU, processes and files, on a network without egress.
- The `<style>` of diagrams kept by the SVG sanitizer, its rules rewritten by `phil/css-sanitizer`.
- Unit, functional and Node tests.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
