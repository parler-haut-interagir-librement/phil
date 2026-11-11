# Changelog

All notable changes to `phil/text-diff-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilTextDiffBundle` and the `phil_text_diff` configuration: `max_edits`, the bound of edits of a comparison;
  `tokenizer`, what the `text_diff` filter compares by when the template names nothing; and `html_sanitizer`, the
  sanitizer of the HTML a template does not mark safe.
- `TextDiffServices`: the public `TextDiff` service, checking the blocks of a document difference with the
  `Normalizer` service of `phil/ujo-bundle` when it is installed, and the Twig extension reading documents with the
  `Ujo` service and rendering them with its `HtmlRenderer` service.
- The Twig filters `text_diff`, two texts by words, lines, paragraphs or HTML, plain text escaped and HTML trusted only
  as `Twig\Markup`, sanitized or escaped otherwise; and `text_diff_document`, two UJO documents, or their JSON, block
  by block.
- The `comparison` Stimulus controller, `assets/src/comparison_controller.js` and `assets/src/lib/marks.js`: the
  inline, old and new views written as `data-text-diff-mode`, the mode buttons pressed, the next and previous change
  marked, focused and brought into view, a replacement as one change, and a live counter; mapped for AssetMapper as
  `@phil/text-diff-bundle`.
- `assets/styles/text-diff.css`: insertions, deletions and the blocks of a document difference, the views and the
  current change, light and dark by the system or `data-theme`, light on paper, its colours custom properties meeting
  the WCAG AA contrast, mapped for AssetMapper as `phil-text-diff/text-diff.css`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
